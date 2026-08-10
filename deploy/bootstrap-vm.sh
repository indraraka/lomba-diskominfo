#!/usr/bin/env bash
set -Eeuo pipefail

[[ "${EUID:-$(id -u)}" -eq 0 ]] || { echo "Jalankan sebagai root." >&2; exit 1; }
deploy_public_key="${1:?Pemakaian: bootstrap-vm.sh '<public-key-deploy>'}"
deploy_user="lomba-deploy"
deploy_root="/home/$deploy_user/lomba-diskominfo"
shared_dir="$deploy_root/shared"
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
caddy_file="${CADDY_FILE:-/home/USER/wa-gateway/deploy/Caddyfile}"
caddy_container="${CADDY_CONTAINER:-wa-gateway-caddy-1}"

docker network inspect wa-gateway_default >/dev/null
if ! id "$deploy_user" >/dev/null 2>&1; then
    useradd --create-home --shell /bin/bash "$deploy_user"
fi
usermod -aG docker "$deploy_user"
install -d -m 700 -o "$deploy_user" -g "$deploy_user" "/home/$deploy_user/.ssh"
touch "/home/$deploy_user/.ssh/authorized_keys"
grep -qxF "$deploy_public_key" "/home/$deploy_user/.ssh/authorized_keys" || printf '%s\n' "$deploy_public_key" >> "/home/$deploy_user/.ssh/authorized_keys"
chown "$deploy_user:$deploy_user" "/home/$deploy_user/.ssh/authorized_keys"
chmod 600 "/home/$deploy_user/.ssh/authorized_keys"
install -d -m 750 -o "$deploy_user" -g "$deploy_user" "$deploy_root"
install -d -m 700 -o "$deploy_user" -g "$deploy_user" "$shared_dir" "$shared_dir/backups" "$deploy_root/releases"

if [[ ! -f "$shared_dir/.env" ]]; then
    app_key="$(openssl rand -base64 32 | tr -d '\n')"
    admin_password="$(openssl rand -hex 24)"
    session_pin="$(shuf -i 100000-999999 -n 1)"
    db_password="$(openssl rand -hex 24)"
    db_root_password="$(openssl rand -hex 24)"
    reverb_id="lomba-$(openssl rand -hex 8)"
    reverb_key="$(openssl rand -hex 16)"
    reverb_secret="$(openssl rand -hex 32)"

    cat > "$shared_dir/.env" <<ENV
APP_NAME="Lomba Diskominfo"
APP_ENV=production
APP_KEY=base64:$app_key
APP_DEBUG=false
APP_URL=https://lomba.indramayukab.go.id
APP_LOCALE=id
APP_FALLBACK_LOCALE=id
ADMIN_EMAIL=admin@lomba.indramayukab.go.id
ADMIN_PASSWORD=$admin_password
LOMBA_SESSION_PIN=$session_pin
APP_MAINTENANCE_DRIVER=cache
APP_MAINTENANCE_STORE=redis
BCRYPT_ROUNDS=12
LOG_CHANNEL=stderr
LOG_LEVEL=info
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=lomba
DB_USERNAME=lomba
DB_PASSWORD=$db_password
DB_ROOT_PASSWORD=$db_root_password
SESSION_DRIVER=database
SESSION_LIFETIME=240
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
CACHE_STORE=redis
CACHE_PREFIX=lomba_
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=$reverb_id
REVERB_APP_KEY=$reverb_key
REVERB_APP_SECRET=$reverb_secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=lomba.indramayukab.go.id
VITE_REVERB_APP_KEY=$reverb_key
VITE_REVERB_HOST=lomba.indramayukab.go.id
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
FILESYSTEM_DISK=local
MAIL_MAILER=log
ENV
    cat > "$shared_dir/bootstrap-credentials.txt" <<CREDS
URL: https://lomba.indramayukab.go.id
Admin email: admin@lomba.indramayukab.go.id
Admin password: $admin_password
PIN seluruh sesi awal: $session_pin
CREDS
    chown "$deploy_user:$deploy_user" "$shared_dir/.env" "$shared_dir/bootstrap-credentials.txt"
    chmod 600 "$shared_dir/.env" "$shared_dir/bootstrap-credentials.txt"
fi

if ! swapon --show=NAME --noheadings | grep -qx '/swapfile'; then
    if [[ ! -f /swapfile ]]; then
        fallocate -l 2G /swapfile
        chmod 600 /swapfile
        mkswap /swapfile >/dev/null
    fi
    swapon /swapfile
    grep -qF '/swapfile none swap sw 0 0' /etc/fstab || printf '%s\n' '/swapfile none swap sw 0 0' >> /etc/fstab
fi

if ! grep -qF '# BEGIN LOMBA DISKOMINFO' "$caddy_file"; then
    cat >> "$caddy_file" <<'CADDY'

# BEGIN LOMBA DISKOMINFO
lomba.indramayukab.go.id {
	encode zstd gzip
	reverse_proxy lomba-app:80
}
# END LOMBA DISKOMINFO
CADDY
fi
docker exec "$caddy_container" caddy validate --config /etc/caddy/Caddyfile
docker exec "$caddy_container" caddy reload --config /etc/caddy/Caddyfile

install -m 644 "$script_dir/lomba-backup.service" /etc/systemd/system/lomba-backup.service
install -m 644 "$script_dir/lomba-backup.timer" /etc/systemd/system/lomba-backup.timer
systemctl daemon-reload
systemctl enable --now lomba-backup.timer >/dev/null

echo "Bootstrap VM selesai. Kredensial awal tersimpan di $shared_dir/bootstrap-credentials.txt"
