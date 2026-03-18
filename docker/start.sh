#!/bin/sh
# Arranca PHP-FPM en background y Nginx en foreground.
# Docker mantiene el contenedor vivo mientras Nginx esté corriendo.
php-fpm -D
nginx -g "daemon off;"
