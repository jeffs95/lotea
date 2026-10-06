#!/bin/sh
#
# Lo que pasa cada vez que arranca un contenedor, antes de servir nada.
set -e

cd /var/www/html

# Las cachés se arman aquí y no al construir la imagen: necesitan las
# variables de entorno de verdad, que solo existen al arrancar. Una imagen con
# la configuración de otro servidor dentro es el clásico «en mi máquina sí».
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Solo el contenedor web migra. Si lo hicieran también el worker y cualquier
# réplica, dos procesos correrían las mismas migraciones a la vez contra la
# misma base, que es como se rompe una tabla a medio crear.
if [ "${LOTEA_MIGRAR_AL_ARRANCAR:-false}" = "true" ]; then
    echo "Aplicando migraciones…"
    php artisan migrate --force
fi

exec "$@"
