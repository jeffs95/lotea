# syntax=docker/dockerfile:1

# Lotea en un contenedor. Sirve igual en un VPS que en el Container Station
# de un NAS: lo único que cambia es quién le pasa las variables de entorno.
#
# Se construye por partes para que la imagen final no cargue con composer, con
# node ni con los compiladores de las extensiones de PHP. Lo que llega al
# servidor es PHP, nginx y el código ya listo.

# ----------------------------------------------------------------------- base
# PHP con todo lo que el proyecto pide, en una capa que usan tanto la
# instalación de dependencias como la imagen final.
#
# Compartirla no es solo ahorrar: composer comprueba que las extensiones
# existan antes de instalar nada, así que si el sitio donde se instala no es
# el mismo donde se va a ejecutar, o falla, o hay que mentirle con
# --ignore-platform-reqs y enterarse del problema en producción.
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
        libpng \
        libjpeg-turbo \
        freetype \
        libwebp \
        icu-libs \
        libzip \
    && apk add --no-cache --virtual .compilar \
        $PHPIZE_DEPS \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libwebp-dev \
        icu-dev \
        libzip-dev \
        postgresql-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        zip \
        exif \
        ftp \
        pdo_pgsql \
        opcache \
        pcntl \
    && apk del .compilar \
    && rm -rf /tmp/*

# bcmath, gd y mbstring las exige composer.json; intl la pide Filament y
# formatea los quetzales; exif la usan medialibrary y spatie/image para leer
# la orientación de las fotos; ftp viene con flysystem-ftp, que quedó del
# almacenamiento anterior; pdo_pgsql habla con la base; pcntl deja que el
# worker atienda la señal de apagado en vez de que lo maten a mitad de un
# trabajo.

# ---------------------------------------------------------------- dependencias
FROM base AS dependencias

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Primero solo los dos archivos de composer: mientras no cambien, Docker
# reutiliza esta capa y no vuelve a bajar nada.
COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# --------------------------------------------------------------------- assets
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

# El tema de Filament importa su hoja desde vendor/ y rastrea las clases que
# se usan en app/ y en resources/views/. Sin el vendor aquí, el build sale sin
# la mitad de los estilos del panel y nadie se entera hasta abrirlo.
COPY --from=dependencias /app/vendor ./vendor

RUN npm run build

# -------------------------------------------------------------------- runtime
FROM base AS runtime

RUN apk add --no-cache nginx supervisor postgresql-client

COPY docker/php.ini /usr/local/etc/php/conf.d/lotea.ini
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
# Por si el bit de ejecución se pierde en el camino —pasa al clonar en
# Windows o al copiar por FTP—, que no dependa de cómo llegó el archivo.
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=dependencias --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Lo único que la aplicación escribe. El resto del árbol puede quedar de solo
# lectura: si algún día algo intenta escribir fuera de aquí, es que algo anda
# mal y conviene que falle.
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && rm -f .env

EXPOSE 8080

# Laravel trae esta ruta para esto mismo: responde 200 cuando la aplicación
# puede arrancar de verdad, no solo cuando el proceso está vivo.
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:8080/up") ? 0 : 1);'

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
