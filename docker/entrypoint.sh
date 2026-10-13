#!/bin/sh
# Entrypoint for the "app" and "worker" containers (docker-compose.yml).
#
# CONTAINER_ROLE=app    prepares the shared volume, creates the SQLite file if
#                       needed, migrates, seeds content once (only while the
#                       content tables are empty), then marks this build ready.
# CONTAINER_ROLE=worker waits for that mark, so only one container ever
#                       migrates the shared SQLite file.
# Both then cache config/routes/views (bootstrap/cache is per container) and
# run the given command.
set -eu
cd /var/www/html

DATA="${APP_DATA:-/data}"
DB="${DB_DATABASE:-$DATA/database/database.sqlite}"
ROLE="${CONTAINER_ROLE:-app}"
BUILD_ID="$(cat /opt/build-id)"
READY="$DATA/database/.ready"

log() { echo "[entrypoint:$ROLE] $*"; }

# storage/ is a symlink to $DATA/storage: fill in the framework directories once,
# and refresh the committed seed data from this image on every start.
mkdir -p "$DATA/database" "$DATA/storage"
cp -rn /opt/storage-template/. "$DATA/storage/"
mkdir -p "$DATA/storage/app/seed-data"
cp -r /opt/storage-template/app/seed-data/. "$DATA/storage/app/seed-data/"
mkdir -p "$DATA/storage/framework/cache/data" "$DATA/storage/framework/sessions" \
    "$DATA/storage/framework/views" "$DATA/storage/logs"

if [ "$ROLE" = "app" ]; then
    if [ ! -f "$DB" ]; then
        log "membuat berkas SQLite $DB"
        touch "$DB"
    fi

    php artisan migrate --force

    if php artisan list --raw | grep -q '^app:seed-content '; then
        rows="$(php -r 'try { $p = new PDO("sqlite:".$argv[1]); echo (int) $p->query("SELECT COUNT(*) FROM mandarin_words")->fetchColumn(); } catch (Throwable $e) { echo 0; }' "$DB")"
        if [ "$rows" = "0" ]; then
            log "tabel konten kosong: menjalankan app:seed-content"
            php artisan app:seed-content || log "PERINGATAN: app:seed-content belum lengkap (lihat pesan di atas); web tetap dijalankan"
        else
            log "konten sudah ada ($rows baris mandarin_words): seeding dilewati"
        fi
    fi

    echo "$BUILD_ID" > "$READY"
else
    log "menunggu container app selesai migrasi (build $BUILD_ID)"
    waited=0
    until [ -f "$DB" ] && [ "$(cat "$READY" 2>/dev/null || true)" = "$BUILD_ID" ]; do
        waited=$((waited + 2))
        if [ "$waited" -ge 600 ]; then
            log "berhenti: app belum siap setelah 10 menit"
            exit 1
        fi
        sleep 2
    done
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

log "mulai: $*"
exec "$@"
