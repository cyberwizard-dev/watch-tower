#!/bin/sh
set -e

# Wait for database if DB_CONNECTION is not sqlite
if [ "${DB_CONNECTION}" != "sqlite" ] && [ -n "${DB_HOST}" ]; then
    echo "Waiting for Database at ${DB_HOST}:${DB_PORT:-5432}..."
    attempt=0
    while [ $attempt -lt 30 ]; do
        if php -r "
            try {
                \$fp = @fsockopen('${DB_HOST}', (int) '${DB_PORT:-5432}', \$errno, \$errstr, 3.0);
                if (\$fp) { fclose(\$fp); echo 'OK'; exit(0); }
            } catch (\Throwable \$e) {}
            exit(1);
        " 2>/dev/null | grep -q 'OK'; then
            echo "Database is ready."
            break
        fi
        attempt=$((attempt + 1))
        echo "Database not ready (attempt $attempt/30), retrying in 2s..."
        sleep 2
    done
fi

# If using SQLite and the file doesn't exist, create it
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_DATABASE_FILE="${DB_DATABASE:-database/database.sqlite}"
    if [ ! -f "$DB_DATABASE_FILE" ]; then
        echo "Creating SQLite database at $DB_DATABASE_FILE..."
        mkdir -p "$(dirname "$DB_DATABASE_FILE")"
        touch "$DB_DATABASE_FILE"
        chown -R www-data:www-data "$(dirname "$DB_DATABASE_FILE")"
        chmod -R 775 "$DB_DATABASE_FILE"
    fi
fi

# Generate an APP_KEY if not provided
if [ -z "${APP_KEY}" ]; then
    echo "No APP_KEY found, generating one..."
    export APP_KEY="$(php artisan key:generate --show --no-interaction)"
fi

echo "Ensuring storage link..."
php artisan storage:link --force || true

echo "Clearing compiled caches..."
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan event:clear || true

echo "Caching configuration..."
php artisan config:cache

echo "Caching routes..."
php artisan route:cache

echo "Caching views..."
php artisan view:cache

echo "Caching events..."
php artisan event:cache

echo "Running migrations..."
php artisan migrate --force

echo "Starting background Queue Worker..."
php artisan queue:work --daemon --verbose &

echo "Starting background Schedule Runner..."
(
    while true; do
        php artisan schedule:run >> /dev/null 2>&1
        sleep 60
    done
) &

echo "Starting Laravel built-in web server..."
# --host=0.0.0.0 binds inside the container to make it accessible externally
# --port=8000 matches EXPOSE and Traefik upstream
exec php artisan serve --host=0.0.0.0 --port=8000
