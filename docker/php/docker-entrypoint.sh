#!/bin/sh
set -e

# Sprawdź czy vendor istnieje (czy composer install został uruchomiony)
if [ ! -d "/var/www/html/vendor" ] || [ ! -f "/var/www/html/vendor/autoload.php" ]; then
    echo "Vendor not found. Running only php-fpm (run composer install first)"
    exec php-fpm
fi

# Sprawdź czy istnieje plik supervisord dla tego serwisu
SERVICE_NAME=${SERVICE_NAME:-default}
SUPERVISORD_CONF="/var/www/html/docker/supervisord/${SERVICE_NAME}.conf"

# Jeśli SERVICE_NAME jest ustawione i istnieje konfiguracja supervisord
if [ -f "$SUPERVISORD_CONF" ]; then
    echo "Starting supervisord with config: $SUPERVISORD_CONF"
    exec /usr/bin/supervisord -c "$SUPERVISORD_CONF"
else
    echo "No supervisord config found, starting php-fpm"
    exec php-fpm
fi
