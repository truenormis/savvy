#!/bin/sh

DATA_DIR=/data
ENV_FILE=/var/www/html/.env

mkdir -p $DATA_DIR
chown -R www-data:www-data $DATA_DIR
chmod 775 $DATA_DIR

if [ -f $DATA_DIR/.env_config ]; then
    cp $DATA_DIR/.env_config $ENV_FILE
else
    APP_KEY="base64:$(openssl rand -base64 32)"

    cat > $ENV_FILE << EOF
APP_NAME=Savvy
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost

APP_KEY=$APP_KEY

DB_CONNECTION=sqlite
DB_DATABASE=$DATA_DIR/database.sqlite

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

BACKUP_PATH=$DATA_DIR/backups
EOF

    touch $DATA_DIR/database.sqlite
    chown www-data:www-data $DATA_DIR/database.sqlite
    chmod 664 $DATA_DIR/database.sqlite

    mkdir -p $DATA_DIR/backups
    chown www-data:www-data $DATA_DIR/backups
    chmod 775 $DATA_DIR/backups

    php artisan migrate --force --seed

    cp $ENV_FILE $DATA_DIR/.env_config
fi

[ -f $DATA_DIR/database.sqlite ] && chown www-data:www-data $DATA_DIR/database.sqlite && chmod 664 $DATA_DIR/database.sqlite
[ ! -d $DATA_DIR/backups ] && mkdir -p $DATA_DIR/backups && chown www-data:www-data $DATA_DIR/backups && chmod 775 $DATA_DIR/backups

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

exec /usr/bin/supervisord -c /etc/supervisord.conf
