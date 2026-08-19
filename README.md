# Lotea

SaaS de gestión para concesionarios que importan vehículos de subastas de EE.UU. a Guatemala.

La idea del producto no es llevar inventario, es **saber cuánto costó realmente cada carro**:
cada VIN es un mini estado de resultados con gastos en dos monedas, impuestos de importación,
taller y comisiones.

## Stack

| | |
|---|---|
| PHP | 8.3 |
| Laravel | 13 |
| Panel | Filament 5 + Filament Shield |
| Base de datos | PostgreSQL 18 |
| Permisos | spatie/laravel-permission (modo *teams*, un juego de roles por empresa) |
| Auditoría | spatie/laravel-activitylog |

## Multi-tenancy

Una sola base de datos; cada concesionario es una fila en `empresas` y todas las tablas de
negocio llevan `empresa_id`. El aislamiento descansa en tres piezas:

- **`App\Support\Tenancy`** — guarda qué empresa está activa en la petición. También sincroniza
  el contexto de roles de spatie, para que nadie termine editando los roles de otro cliente.
- **`App\Models\Concerns\PerteneceAEmpresa`** — el trait que usa *todo* modelo de negocio.
  Filtra las consultas, rellena `empresa_id` al crear y prohíbe mover un registro de una
  empresa a otra.
- **`App\Models\Concerns\EsCatalogoCompartido`** — para marcas y líneas: `empresa_id` nulo son
  las que mantiene Lotea para todos, con valor son las que agregó ese cliente.

Para leer datos de varias empresas a la vez (panel central, reportes globales) hay que pedirlo
explícitamente con `Tenancy::sinFiltro(fn () => ...)`. Es a propósito: así se ve en el diff.

> Los tests de `tests/Feature/AislamientoEntreEmpresasTest.php` y `RolesPorEmpresaTest.php`
> cuidan justamente esto. Si se ponen en rojo, un cliente puede ver los datos de otro.
> No se borran ni se marcan como skipped.

## Levantar el proyecto

```bash
composer install
cp .env.example .env && php artisan key:generate
createdb -U postgres lotea
php artisan migrate
php artisan shield:generate --all --panel=admin
php artisan db:seed
php artisan serve --port=8010
```

Panel en `http://localhost:8010/app` — usuario de prueba `dueno@lotea.test` / `password`.

## Tests

```bash
php artisan test
```

Corren contra PostgreSQL (base `lotea_test`), no SQLite, para no descubrir en producción las
diferencias entre motores.

## El ciclo de la unidad

Todo gira alrededor de una máquina de estados por VIN, en `App\Enums\EstadoUnidad`:

```
COMPRADA → EN_TÍTULO → TRÁNSITO_USA → BODEGA_USA → EMBARCADA →
EN_ADUANA → TRÁNSITO_LOCAL → RECIBIDA → EN_TALLER → LISTA →
PUBLICADA → RESERVADA → VENDIDA → ENTREGADA → EN_CARTERA
```

El enum decide qué transiciones existen y `App\Actions\CambiarEstadoUnidad` es el único
camino para moverse entre ellas: valida, deja historial con fecha, usuario y días en la
etapa anterior, y sella las fechas hito una sola vez. De ahí salen el aging, el capital
dormido y los días de rotación.

Desde `EMBARCADA` la unidad ya se puede publicar: la preventa es negocio real y es lo que
recorta los días de inventario.

## El costeo

Cada gasto se guarda en la moneda en que se pagó, con el tipo de cambio del documento y el
monto en quetzales. Convertir "al final" es como se pierde margen sin darse cuenta.

Dos reglas del módulo de dinero:

- **Nada se borra.** Un gasto equivocado se anula con motivo, usuario y fecha
  (`App\Actions\AnularCosto`). Si se pudiera borrar, cualquiera podría maquillar el margen
  de una unidad sin dejar rastro.
- **El reparto cuadra exacto.** `App\Actions\ProrratearGasto` divide el flete de un
  contenedor entre sus unidades y le entrega el centavo residual a la primera, para que la
  suma de las porciones dé exactamente el total.

La ficha de rentabilidad (`/unidades/{id}/rentabilidad`) es el estado de resultados de un
solo carro, y está detrás del permiso `ver_costos_unidad`: el vendedor que conoce el costo
negocia contra su propio patrón.

## El portal público

Cada concesionario tiene su propio sitio, servido desde el mismo Laravel:

- **En producción** se resuelve por el dominio del cliente (`empresas.dominio`).
- **En desarrollo**, o mientras no compran dominio, por `/v/{slug}`.

Las dos formas pasan por `ResolverEmpresaDelPortal`, que fija la empresa activa antes de
tocar un registro. Los enlaces se generan con `App\Support\PortalUrl`, que sabe cuál de las
dos usar.

El catálogo muestra solo unidades publicadas, incluidas las que vienen en camino: la
preventa se habilita desde `EMBARCADA` y sale marcada como **Próximamente**.

Cada ficha lleva `schema.org/Car`, Open Graph para que se vea bien al compartir por
WhatsApp, calculadora de cuota y un botón de WhatsApp con el mensaje ya escrito y el número
de stock adentro. Los formularios caen en `leads` con el cronómetro de primera respuesta
corriendo.

## Estado

Fases 1 a 5 terminadas: fundación y tenancy, catálogos, la unidad con su ciclo de vida, el
tablero del patio, el costeo con multimoneda y prorrateo, y el portal público con CRM de
prospectos.

Falta de la v1: ventas y comisiones, taller con órdenes de trabajo, y cartera de crédito
propio.
