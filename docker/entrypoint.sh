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
APP_NAME=Money
#APP_ENV=production
#APP_DEBUG=false
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

APP_KEY=$APP_KEY

DB_CONNECTION=sqlite
DB_DATABASE=$DATA_DIR/database.sqlite

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
EOF

    touch $DATA_DIR/database.sqlite
    chown www-data:www-data $DATA_DIR/database.sqlite
    chmod 664 $DATA_DIR/database.sqlite

    php artisan migrate --force --seed

    cp $ENV_FILE $DATA_DIR/.env_config
fi

[ -f $DATA_DIR/database.sqlite ] && chown www-data:www-data $DATA_DIR/database.sqlite && chmod 664 $DATA_DIR/database.sqlite

php artisan migrate --force

exec /usr/bin/supervisord -c /etc/supervisord.conf
