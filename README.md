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

## Ventas

La venta es donde el margen deja de ser una aspiración. Mientras la unidad no se vende, la
rentabilidad usa el precio de lista; en cuanto se cierra una venta, usa el precio real de
cierre (`Unidad::$precio_para_margen`).

`App\Actions\RegistrarVenta` hace tres cosas en una sola transacción: mueve la unidad a
*Vendida*, calcula la comisión y la registra como gasto de la unidad. Anular la venta
(`AnularVenta`) devuelve el carro al patio y anula esa comisión, sin borrar nada.

**La comisión se calcula sobre la utilidad, no sobre el precio.** Es lo que alinea al
vendedor con el dueño: si regala precio, se corta su propia comisión. Hay un test que lo
comprueba.

## Los dos paneles

| Panel | Ruta | Quién entra |
|---|---|---|
| **Operación** | `/app/{empresa}` | El concesionario: su inventario, costos, ventas y prospectos |
| **Central** | `/central` | Lotea: concesionarios, planes, cobros y métricas del negocio |

El acceso al panel central sale de la bandera `users.es_operador`, **no de un rol**. Los roles
viven por empresa y los administra el propio cliente; si el acceso dependiera de uno, un
cliente podría dárselo a sí mismo.

Dar de alta un concesionario desde `/central` deja todo listo de una vez: catálogos, roles,
sucursal principal, el usuario dueño con sus permisos y el primer cobro.

**La suspensión corta de verdad.** Un cliente suspendido no entra a su panel y su portal
público deja de responder, pero no pierde un solo dato: al reactivarlo sigue donde quedó.

## Soporte

Cuando un cliente dice *"no puedo agregar un vehículo"*, la causa casi siempre es un permiso
que le falta al rol. Para eso hay dos herramientas en el panel central, y ninguna requiere
entrar a la cuenta del cliente:

- **Diagnóstico** (`/central/diagnostico`) — elegís un usuario y ves qué puede y qué no,
  módulo por módulo, en español y no en `ViewAny:Unidad`. El botón *Copiar diagnóstico* deja
  un resumen listo para pegarle al dueño por WhatsApp.
- **Bandeja de soporte** (`/central/soporte`) — lo que reportan los clientes desde su panel,
  con el contexto capturado solo: quién, con qué rol, en qué pantalla. Desde cada ticket hay
  un enlace directo al diagnóstico de quien lo reportó.

Del lado del cliente, **Soporte** en su menú: reporta en dos líneas y ve la respuesta ahí
mismo. Pedir ayuda no depende del rol —si el mecánico no puede reportar, el problema llega
tarde y por WhatsApp—, pero cada quien ve solo sus propios reportes.

> `App\Policies\TicketPolicy` está escrita a mano. `php artisan shield:generate` la
> sobreescribe: si volvés a correrlo, revisá que siga como está.

## Usuarios de prueba

| Usuario | Contraseña | Para qué |
|---|---|---|
| `operador@lotea.gt` | `password` | Panel central: el negocio de vender Lotea |
| `dueno@lotea.test` | `password` | Ve todo de su concesionario, incluidos costos y márgenes |
| `vendedor@lotea.test` | `password` | Ve inventario, ventas y clientes — **ningún costo** |

Entrar con el vendedor es la forma más rápida de comprobar la regla de negocio más
importante del sistema.

## Taller, caja y cartera

**Empleados** viven aparte de `users`: el mecánico casi nunca tiene usuario del sistema. Su
`costo_hora` es de donde el taller saca la mano de obra que le carga a cada unidad.

**Caja** por sucursal, en quetzales y en dólares. El saldo se calcula, nunca se guarda. Los
movimientos no se borran y los traslados son dos movimientos que se apuntan entre sí, así
que anular uno anula los dos. Los arqueos registran la diferencia en lugar de ajustarla.

**Órdenes de trabajo** con mano de obra, repuestos y trabajos a terceros. Al cerrarlas, el
costo pasa a `costos_unidad` (un renglón por tipo) y la bandera `costos_descargados` impide
duplicarlo. Anular la orden le devuelve ese costo a la unidad.

**Cartera** de crédito propio con amortización francesa: el residuo del redondeo cae en la
última cuota para que la suma del capital dé exactamente lo financiado. Los pagos entran a
caja, la mora se calcula al día (no se guarda, porque cambia cada día) y al cobrar la última
cuota el crédito se cancela solo.

## El QR del parabrisas

Cada unidad nace con un código corto único (`WVD299`) impreso junto a un QR que se pega en
el parabrisas. La ruta es `/u/{codigo}` y decide a dónde va cada quien:

- **Un cliente en el patio** cae en la ficha pública, con fotos, precio y WhatsApp.
- **Alguien del concesionario**, con su sesión abierta, cae en la ficha interna con el botón
  de vender.

Una sola etiqueta, sin que nadie tenga que saber cuál escanear. Si la unidad todavía no está
publicada, en lugar de un 404 el cliente ve un aviso con el botón de WhatsApp; y si el
concesionario está suspendido, su QR deja de responder.

El código no usa vocales ni los caracteres que se confunden al dictarlo por teléfono (O/0,
I/1, S/5). La hoja de etiquetas se imprime desde el listado de unidades y **no lleva el
precio**: el precio cambia y nadie va a reimprimir cuarenta etiquetas. Lo que cambia lo
muestra el QR, que siempre está al día.

En la pantalla de venta, el buscador de unidad acepta ese mismo código, así que un lector de
códigos conectado al puerto USB funciona sin configurar nada.

## Estado

Terminado: fundación y tenancy, catálogos, la unidad con su ciclo de vida, el tablero del
patio, el costeo con multimoneda y prorrateo, el portal público con CRM, clientes y ventas
con comisiones, empleados, caja por sucursal, taller y cartera de crédito propio.

Del plan original queda pendiente: nómina de Guatemala, inversionistas por unidad,
inventario de repuestos, reportes avanzados y el "qué comprar".
