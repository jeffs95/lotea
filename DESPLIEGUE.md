# Desplegar Lotea en un servidor propio

Sirve igual para un VPS que para el Container Station de un NAS. Lo único que
cambia es quién pone las variables de entorno y cómo entra el tráfico.

## Lo que hay en el repositorio

| Archivo | Para qué |
| --- | --- |
| `Dockerfile` | Construye la imagen: PHP 8.3 + nginx, con el código y los assets adentro |
| `docker-compose.yml` | Los tres servicios: web, worker y base |
| `docker/` | La configuración de nginx, PHP, supervisor y el arranque |
| `.env.docker.ejemplo` | Las variables que hay que llenar |

## Los tres servicios

- **app** — nginx y PHP sirviendo el panel y los portales. Es el único que
  aplica las migraciones al arrancar.
- **worker** — la cola. Si se apaga, las fotos nuevas se quedan sin las
  versiones que muestra el portal.
- **base** — PostgreSQL 18, con los datos en un volumen.

## Arrancarlo

```bash
cp .env.docker.ejemplo .env
# llenar APP_KEY, DB_PASSWORD y las credenciales de R2
docker compose up -d --build
```

La primera vez, el usuario operador:

```bash
docker compose exec app php artisan db:seed --class=OperadorSeeder
```

## Lo que no vive en el servidor

Las fotos y los documentos están en Cloudflare R2, no en el disco de la
máquina. Por eso un servidor chico alcanza, y por eso **mudarse de servidor no
mueve archivos**: se levanta el nuevo apuntando al mismo cubo y ya están.

## El tráfico

El contenedor escucha en el 8080 y no sabe nada de certificados. Delante va
uno de estos:

- **VPS con Coolify** — se encarga del SSL, incluidos los dominios propios de
  cada concesionario.
- **NAS** — hace falta Cloudflare Tunnel, porque la IP es privada. El túnel
  también resuelve el SSL.

Los dominios propios importan: cada concesionario puede entrar por el suyo
(`ResolverEmpresaDelPortal` lo resuelve por el *host* de la petición), así que
el que termine el SSL tiene que poder emitir certificados para dominios que se
agregan sobre la marcha.

## En un NAS con Container Station

Se usa el otro compose, que no publica puertos y trae el túnel:

Primero todo menos el túnel, para probarlo por dentro:

```bash
docker compose -f docker-compose.nas.yml up -d --build
```

Y cuando ya responde, se le abre la puerta:

```bash
docker compose -f docker-compose.nas.yml --profile publico up -d
```

Para eso hace falta crear el túnel en Cloudflare (Zero Trust → Networks →
Tunnels), copiar su token al `.env` y, dentro del túnel, apuntar cada nombre
público a `http://app:8080`. Así se agregan también los dominios propios de
los concesionarios, sin tocar el NAS.

Tres cosas que muerden en un NAS y no en un VPS:

- **El puerto 8080 ya es de QTS.** Por eso este compose no publica ninguno:
  el túnel llega por la red interna de Docker.
- **La memoria.** Si el NAS tiene 4 GB o menos, `npm run build` puede morirse
  al compilar los estilos. Se resuelve construyendo la imagen en otra máquina
  y cargándola, o agregando memoria de intercambio.
- **La arquitectura.** Si el NAS es ARM en vez de Intel, la construcción es
  bastante más lenta pero funciona; las imágenes base existen para los dos.

## Desplegar cambios

```bash
git pull && docker compose up -d --build
```

Reconstruye la imagen, aplica migraciones y reemplaza los contenedores. Con
Coolify esto pasa solo con cada `git push`.

## Variables que no se pueden olvidar

- `APP_KEY` — si cambia, las sesiones y lo cifrado dejan de poder leerse.
- `LOTEA_DISCO_PRIVADO` — sin esto los dos cubos son el mismo y los documentos
  de identidad terminan donde los sirve el CDN.
- `SESSION_DOMAIN` vacío — cada concesionario entra por su dominio, y fijar
  uno dejaría a los demás sin poder iniciar sesión.
