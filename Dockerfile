# ─────────────────────────────────────────────────────────────────
#  KitchenLink — Contenedor de la App
#  PHP 8.1-FPM + Nginx + mysqli
#
#  Uso local:
#    docker compose up --build
# ─────────────────────────────────────────────────────────────────

FROM php:8.1-fpm-alpine

# Instalar Nginx y la extensión mysqli para conectar a MySQL
RUN apk add --no-cache nginx \
    && docker-php-ext-install mysqli

# Configuración de Nginx
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Código de la aplicación
WORKDIR /var/www/html
COPY . .

# Permisos correctos para Nginx + PHP-FPM
RUN chown -R www-data:www-data /var/www/html

# Puerto HTTP
EXPOSE 80

# Script que arranca PHP-FPM en background y Nginx en foreground
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

CMD ["/start.sh"]
