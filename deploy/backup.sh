#!/usr/bin/env bash
set -Eeuo pipefail

deploy_root="${DEPLOY_ROOT:-$HOME/lomba-diskominfo}"
shared_dir="$deploy_root/shared"
env_file="$shared_dir/.env"
compose_file="$deploy_root/current/compose.production.yml"
backup_dir="$shared_dir/backups"
image_tag="$(cat "$shared_dir/current-image")"

[[ "$backup_dir" == "$deploy_root/shared/backups" ]] || exit 1
mkdir -p "$backup_dir"
chmod 700 "$backup_dir"
export APP_IMAGE="ghcr.io/indraraka/lomba-diskominfo:$image_tag"
export APP_ENV_FILE="$env_file"
compose=(docker compose --project-name lomba-diskominfo --env-file "$env_file" -f "$compose_file")

target="$backup_dir/daily-$(date -u +%Y%m%dT%H%M%SZ).sql.gz"
tmp="$target.tmp"
# Variabel pada perintah berikut diekspansi oleh shell di container MySQL.
# shellcheck disable=SC2016
"${compose[@]}" exec -T mysql sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers "$MYSQL_DATABASE"' | gzip -9 > "$tmp"
gzip -t "$tmp"
mv "$tmp" "$target"
find "$backup_dir" -mindepth 1 -maxdepth 1 -type f -name '*.sql.gz' -mtime +14 -delete
