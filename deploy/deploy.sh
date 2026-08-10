#!/usr/bin/env bash
set -Eeuo pipefail

requested_tag="${1:?Pemakaian: deploy.sh <commit-sha|previous> [release-dir]}"
deploy_root="${DEPLOY_ROOT:-$HOME/lomba-diskominfo}"
shared_dir="$deploy_root/shared"
releases_dir="$deploy_root/releases"
env_file="$shared_dir/.env"

if [[ "$requested_tag" == "previous" ]]; then
    requested_tag="$(cat "$shared_dir/previous-image" 2>/dev/null || true)"
    [[ -n "$requested_tag" ]] || { echo "Tidak ada image sebelumnya untuk rollback." >&2; exit 1; }
fi

release_dir="${2:-$releases_dir/$requested_tag}"
compose_file="$release_dir/compose.production.yml"
[[ -f "$env_file" ]] || { echo "File environment tidak ditemukan: $env_file" >&2; exit 1; }
[[ -f "$compose_file" ]] || { echo "Compose production tidak ditemukan: $compose_file" >&2; exit 1; }
[[ "$release_dir" == "$releases_dir/"* ]] || { echo "Release harus berada di dalam $releases_dir" >&2; exit 1; }
docker network inspect wa-gateway_default >/dev/null

export APP_IMAGE="ghcr.io/indraraka/lomba-diskominfo:$requested_tag"
export APP_ENV_FILE="$env_file"
compose=(docker compose --project-name lomba-diskominfo --env-file "$env_file" -f "$compose_file")
previous_tag="$(cat "$shared_dir/current-image" 2>/dev/null || true)"

wait_healthy() {
    local service="$1" attempts="${2:-40}" id status
    id="$("${compose[@]}" ps -q "$service")"
    [[ -n "$id" ]] || return 1
    for ((attempt=1; attempt<=attempts; attempt++)); do
        status="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$id")"
        [[ "$status" == "healthy" || "$status" == "running" ]] && return 0
        [[ "$status" == "unhealthy" || "$status" == "exited" || "$status" == "dead" ]] && return 1
        sleep 3
    done
    return 1
}

bring_app_up() {
    for _ in {1..15}; do
        if "${compose[@]}" exec -T app php artisan up --no-interaction >/dev/null 2>&1; then
            return 0
        fi
        sleep 2
    done
    return 1
}

rollback_on_error() {
    local exit_code=$?
    trap - ERR
    echo "Deployment gagal; mencoba memulihkan image sebelumnya." >&2
    if [[ -n "$previous_tag" && "$previous_tag" != "$requested_tag" ]]; then
        export APP_IMAGE="ghcr.io/indraraka/lomba-diskominfo:$previous_tag"
        "${compose[@]}" pull app || true
        "${compose[@]}" up -d --no-deps app || true
        bring_app_up || true
    else
        bring_app_up || true
    fi
    exit "$exit_code"
}
trap rollback_on_error ERR

mkdir -p "$shared_dir/backups" "$releases_dir"
chmod 700 "$shared_dir" "$shared_dir/backups"

"${compose[@]}" pull app mysql redis
"${compose[@]}" up -d mysql redis
wait_healthy mysql 60
wait_healthy redis 30

if [[ -f "$shared_dir/.bootstrapped" ]]; then
    backup_tmp="$shared_dir/backups/predeploy-$(date -u +%Y%m%dT%H%M%SZ).sql.gz.tmp"
    # Variabel pada perintah berikut diekspansi oleh shell di container MySQL.
    # shellcheck disable=SC2016
    "${compose[@]}" exec -T mysql sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers "$MYSQL_DATABASE"' | gzip -9 > "$backup_tmp"
    gzip -t "$backup_tmp"
    mv "$backup_tmp" "${backup_tmp%.tmp}"
fi

old_app_id="$("${compose[@]}" ps -q app || true)"
if [[ -n "$old_app_id" ]]; then
    "${compose[@]}" exec -T app php artisan down --retry=30 --no-interaction
fi

"${compose[@]}" run --rm app php artisan migrate --force --no-interaction
if [[ ! -f "$shared_dir/.bootstrapped" ]]; then
    "${compose[@]}" run --rm app php artisan db:seed --class='Database\\Seeders\\ProductionBootstrapSeeder' --force --no-interaction
    touch "$shared_dir/.bootstrapped"
    chmod 600 "$shared_dir/.bootstrapped"
fi

"${compose[@]}" up -d --no-deps app
bring_app_up
wait_healthy app 60
"${compose[@]}" exec -T app supervisorctl status | grep -Eq '^php-fpm[[:space:]]+RUNNING'
"${compose[@]}" exec -T app supervisorctl status | grep -Eq '^nginx[[:space:]]+RUNNING'
"${compose[@]}" exec -T app supervisorctl status | grep -Eq '^queue[[:space:]]+RUNNING'
"${compose[@]}" exec -T app supervisorctl status | grep -Eq '^reverb[[:space:]]+RUNNING'

curl --fail --silent --show-error --retry 24 --retry-delay 5 --retry-all-errors \
    "${HEALTH_URL:-https://lomba.indramayukab.go.id/up}" >/dev/null

if [[ -n "$previous_tag" && "$previous_tag" != "$requested_tag" ]]; then
    printf '%s\n' "$previous_tag" > "$shared_dir/previous-image"
fi
printf '%s\n' "$requested_tag" > "$shared_dir/current-image"
chmod 600 "$shared_dir/current-image" "$shared_dir/previous-image" 2>/dev/null || true
ln -sfn "$release_dir" "$deploy_root/current.next"
mv -Tf "$deploy_root/current.next" "$deploy_root/current"

mapfile -t old_releases < <(find "$releases_dir" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' | sort -rn | tail -n +6 | cut -d' ' -f2-)
for old_release in "${old_releases[@]}"; do
    [[ "$old_release" == "$releases_dir/"* ]] && rm -rf -- "$old_release"
done
docker image prune -f >/dev/null
trap - ERR
echo "Deployment $requested_tag berhasil."
