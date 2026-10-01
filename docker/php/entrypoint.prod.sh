#!/bin/sh
# Entrypoint для app / horizon / reverb / scheduler.
# Prod (root): chown storage → кэш → gosu/php-fpm.
# Local (APP_ENV=local): без config/view:cache; при root — только chown.
set -e

cd /var/www/handyman

if [ ! -f .env ]; then
    echo "FATAL: /var/www/handyman/.env не смонтирован. См. docs/DEPLOY.md" >&2
    exit 1
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "FATAL: APP_KEY не задан в .env (php artisan key:generate --show)" >&2
    exit 1
fi

# local | production | … — из файла .env (не из getenv: в prod env_file ≠ файл на диске)
APP_ENV_VALUE=$(grep -E '^APP_ENV=' .env | head -1 | cut -d= -f2- | tr -d '\r' | tr -d '"' | tr -d "'")
IS_LOCAL=0
if [ "$APP_ENV_VALUE" = "local" ] || [ "$APP_ENV_VALUE" = "development" ]; then
    IS_LOCAL=1
fi

echo "[entrypoint] ожидание базы данных..."
i=0
until php -r '
    require "/var/www/handyman/vendor/autoload.php";
    Dotenv\Dotenv::createImmutable("/var/www/handyman")->safeLoad();
    $dsn = sprintf("mysql:host=%s;port=%s", $_ENV["DB_HOST"] ?? "mysql", $_ENV["DB_PORT"] ?? 3306);
    try { new PDO($dsn, $_ENV["DB_USERNAME"] ?? "", $_ENV["DB_PASSWORD"] ?? ""); exit(0); }
    catch (Throwable $e) { exit(1); }
' 2>/dev/null; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then
        echo "FATAL: база данных недоступна после 60 попыток" >&2
        exit 1
    fi
    sleep 2
done
echo "[entrypoint] база данных доступна"

# Tomа/bind-mount часто от UID 33 или root — www-data (1000) писать не может.
ensure_writable_storage() {
    mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/public \
        bootstrap/cache
    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
    chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true
}

run_as_app_user() {
    if [ "$(id -u)" = "0" ]; then
        ensure_writable_storage
        if [ "$1" = "php-fpm" ]; then
            exec "$@"
        fi
        exec gosu www-data "$@"
    fi
    exec "$@"
}

run_artisan() {
    if [ "$(id -u)" = "0" ]; then
        gosu www-data php artisan "$@"
    else
        php artisan "$@"
    fi
}

if [ "$(id -u)" = "0" ]; then
    ensure_writable_storage
fi

run_artisan package:discover --ansi
run_artisan storage:link --force >/dev/null 2>&1 || true

if [ "$IS_LOCAL" = "1" ]; then
    echo "[entrypoint] local — пропуск config/route/view/event:cache"
    run_as_app_user "$@"
fi

# Отдельный optimize:clear не нужен: *:cache сами чистят свой кэш, а
# optimize:clear ещё и делает cache:clear — сбрасывал бы Redis-кэш на старте
# каждого из 4 контейнеров.
echo "[entrypoint] прогрев кэшей..."
run_artisan config:cache
run_artisan route:cache
run_artisan view:cache
run_artisan event:cache

run_as_app_user "$@"
