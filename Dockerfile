#  KitchenLink — Contenedor de la App
#  PHP 8.1-FPM + Nginx + mysqli
#
#  face-api.js corre 100% en el browser — no se necesita Node.js ni
#  ningún servicio extra. Este contenedor solo sirve los archivos
#  estáticos del modelo (src/face-models/) y los PHP APIs del checador.
#
#  Uso local:
#    docker compose up --build

FROM php:8.1-fpm-alpine

# ── Sistema base ──
# - nginx:       servidor web
# - tzdata:      zona horaria para que los timestamps del checador sean correctos
# - libpng-dev:  dependencia de gd (por si en algún sprint se generan reportes con gráficas)
RUN apk add --no-cache \
        nginx \
        tzdata \
        libpng-dev \
    && docker-php-ext-install mysqli

# ── Zona horaria (importante para attendance_records.timestamp) ──
# Ajusta a tu zona si el servidor está en otro huso
ENV TZ=America/Monterrey
RUN cp /usr/share/zoneinfo/$TZ /etc/localtime \
    && echo $TZ > /etc/timezone

# ── Configuración de Nginx ──
COPY docker/nginx.conf /etc/nginx/nginx.conf

# ── Código de la aplicación ──
WORKDIR /var/www/html
COPY . .

# ── Permisos ──
# www-data necesita leer los modelos de face-api.js (src/face-models/)
# y escribir en ningún lado (todos los descriptores van a MySQL)
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html/src/face-models -type f -exec chmod 644 {} \; 2>/dev/null || true

# ── Puerto HTTP ──
# HTTPS se termina en el load balancer / Cloud Armor de GCP;
# internamente el contenedor solo necesita el 80.
# (getUserMedia en tablets requiere HTTPS — lo provee el LB, no este contenedor)
EXPOSE 80

# ── Entrypoint ──
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

CMD ["/start.sh"]
