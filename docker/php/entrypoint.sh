#!/bin/sh
set -eu

media_directory=/var/www/html/storage/app/public
permissions_marker="$media_directory/.permissions-v1"

mkdir -p "$media_directory"

if [ ! -e "$permissions_marker" ]; then
    chown -R www-data:www-data "$media_directory"
    su-exec www-data touch "$permissions_marker"
fi

if [ "${1:-}" = "php" ]; then
    exec su-exec www-data "$@"
fi

exec docker-php-entrypoint "$@"
