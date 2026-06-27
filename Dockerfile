# ============================================================
#  PILAR — Contenedor App
#  PHP 8.1-FPM + Nginx + mysqli  (mismo stack que KitchenLink)
#
#  face-api.js corre 100% en el browser.
#  Los modelos en src/face-models/ se sirven como estáticos.
#  getUserMedia requiere HTTPS — lo termina el LB/Nginx externo.
# ============================================================
FROM php:8.1-fpm-alpine

# ── Dependencias ──
RUN apk add --no-cache \
        nginx \
        tzdata \
        libpng-dev \
    && docker-php-ext-install mysqli

# ── Zona horaria (timestamps del checador) ──
ENV TZ=America/Monterrey
RUN cp /usr/share/zoneinfo/$TZ /etc/localtime \
    && echo $TZ > /etc/timezone

# ── Nginx ──
COPY docker/nginx.conf /etc/nginx/nginx.conf

# ── Código ──
WORKDIR /var/www/html
COPY . .

# ── Permisos ──
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html/src/face-models -type f -exec chmod 644 {} \; 2>/dev/null || true

EXPOSE 80

COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

CMD ["/start.sh"]
