#!/bin/sh
set -eu

DATA_DIR=/data
ENV_FILE=/var/www/html/.env

mkdir -p "$DATA_DIR"
chmod 775 "$DATA_DIR" 2>/dev/null || true

if [ -f "$DATA_DIR/.env_config" ]; then
    cp "$DATA_DIR/.env_config" "$ENV_FILE"
else
    APP_KEY="base64:$(openssl rand -base64 32)"

    cat > "$ENV_FILE" << EOF
APP_NAME=Savvy
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost

APP_KEY=$APP_KEY

LOG_CHANNEL=stderr

DB_CONNECTION=sqlite
DB_DATABASE=$DATA_DIR/database.sqlite

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

DB_QUEUE_CONNECTION=sqlite_queue
DB_QUEUE_DATABASE=$DATA_DIR/queue.sqlite
DB_CACHE_CONNECTION=sqlite_cache
DB_CACHE_LOCK_CONNECTION=sqlite_cache
DB_CACHE_DATABASE=$DATA_DIR/cache.sqlite
SESSION_CONNECTION=sqlite_sessions
DB_SESSIONS_DATABASE=$DATA_DIR/sessions.sqlite

BACKUP_PATH=$DATA_DIR/backups
UPLOAD_ROOT=$DATA_DIR/uploads
EOF

    for f in database queue cache sessions; do
        touch "$DATA_DIR/$f.sqlite"
        chmod 664 "$DATA_DIR/$f.sqlite"
    done

    mkdir -p "$DATA_DIR/backups"
    chmod 775 "$DATA_DIR/backups"

    mkdir -p "$DATA_DIR/uploads"
    chmod 775 "$DATA_DIR/uploads"

    php artisan migrate --force --seed

    cp "$ENV_FILE" "$DATA_DIR/.env_config"
fi

[ -f "$DATA_DIR/database.sqlite" ] && chmod 664 "$DATA_DIR/database.sqlite"
[ ! -d "$DATA_DIR/backups" ] && mkdir -p "$DATA_DIR/backups" && chmod 775 "$DATA_DIR/backups"
[ ! -d "$DATA_DIR/uploads" ] && mkdir -p "$DATA_DIR/uploads" && chmod 775 "$DATA_DIR/uploads"

if ! grep -q '^UPLOAD_ROOT=' "$ENV_FILE"; then
    echo "UPLOAD_ROOT=$DATA_DIR/uploads" >> "$ENV_FILE"
    grep -q '^UPLOAD_ROOT=' "$DATA_DIR/.env_config" 2>/dev/null || echo "UPLOAD_ROOT=$DATA_DIR/uploads" >> "$DATA_DIR/.env_config"
fi

for f in queue cache sessions; do
    [ -f "$DATA_DIR/$f.sqlite" ] || { touch "$DATA_DIR/$f.sqlite"; chmod 664 "$DATA_DIR/$f.sqlite"; }
done

if ! grep -q '^DB_QUEUE_CONNECTION=' "$ENV_FILE"; then
    cat >> "$ENV_FILE" << EOF
DB_QUEUE_CONNECTION=sqlite_queue
DB_QUEUE_DATABASE=$DATA_DIR/queue.sqlite
DB_CACHE_CONNECTION=sqlite_cache
DB_CACHE_LOCK_CONNECTION=sqlite_cache
DB_CACHE_DATABASE=$DATA_DIR/cache.sqlite
SESSION_CONNECTION=sqlite_sessions
DB_SESSIONS_DATABASE=$DATA_DIR/sessions.sqlite
EOF
    cp "$ENV_FILE" "$DATA_DIR/.env_config"
fi

php artisan migrate --force
php artisan app:ensure-shards

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan currencies:update --no-interaction || true

exec /usr/bin/supervisord -c /etc/supervisord.conf
