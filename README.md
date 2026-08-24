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
php artisan migrate --seed
php artisan storage:link
php artisan serve --port=8010
```

El seed genera los permisos del panel, las marcas y líneas, los tres planes y la cuenta de
Lotea. No siembra concesionarios ni vehículos: los clientes se dan de alta desde el panel
central y cada uno mete su propio inventario.

Panel central en `http://localhost:8010/central`. La cuenta sale de `OperadorSeeder`, que
lee el entorno:

| Variable | Por defecto |
|---|---|
| `LOTEA_OPERADOR_EMAIL` | `jeffersonjuarez0101@gmail.com` |
| `LOTEA_OPERADOR_PASSWORD` | `password` |

`APP_URL` tiene que coincidir con el puerto donde corre el servidor. Si no, los archivos ya
subidos se quedan cargando para siempre en los formularios: el navegador los pide a un puerto
donde no hay nada escuchando.

## Dónde viven los archivos

Las fotos y los documentos de las unidades, y los logos de los concesionarios, van al disco que
diga `LOTEA_DISCO_ARCHIVOS`:

| Valor | Dónde |
|---|---|
| `public` (por defecto) | En el servidor, bajo `storage/app/public` |
| `ftp_documentos` | En el FTP que definan las variables `FTP_*` |

```bash
php artisan lotea:probar-ftp
```

Escribe un archivo, lo lee, lo borra y dice cuánto tardó cada paso. Es lo primero que hay que
correr al configurar un servidor nuevo o cuando algo huele a VPN caída.

**Un disco FTP no tiene URL pública**, así que los archivos no se sirven directo: pasan por
`/archivo/{media}` y `/marca/{slug}/{tipo}`. Eso trae dos cosas:

- **Cada archivo se autoriza.** Las fotos de una unidad publicada son del catálogo y las ve
  cualquiera; las fotos de subasta, los documentos y las fotos de lo que no está publicado solo
  las ve gente del concesionario. Con el disco público, el título de un carro quedaba accesible
  a quien diera con la URL.
- **La primera lectura deja copia local** en `storage/app/cache-archivos`. El portal muestra
  decenas de fotos por visita y pedirlas al FTP cada vez lo pondría de rodillas. Esa carpeta no
  es la fuente de verdad: se puede borrar entera y se vuelve a llenar sola.

> Si el FTP está en una red interna (una IP `172.17.x`), el servidor de Lotea tiene que estar
> dentro de esa red o con VPN. La copia local salva las lecturas, pero **subir** siempre
> necesita alcanzar el FTP.

## Subirlo a producción

```bash
php artisan migrate --force --seed
php artisan storage:link
php artisan optimize
```

Antes del primer seed conviene definir `LOTEA_OPERADOR_EMAIL` y `LOTEA_OPERADOR_PASSWORD`:
la contraseña por defecto sirve para desarrollar, no para un servidor abierto a internet.

El seed es idempotente —usa `updateOrCreate`— así que volver a correrlo tras un despliegue no
duplica nada. Lo que sí hace es **devolver la contraseña del operador al valor del entorno**,
así que si la cambiaste desde el panel, actualizá la variable o dejá de sembrar.

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

## Auditoría del dinero

Todo lo que mueve plata deja rastro: gastos de unidad, gastos compartidos, ventas, movimientos
de caja, pagos de cuota, órdenes de trabajo y los precios y estados de las unidades. Cada
registro guarda quién, cuándo, y el valor viejo junto al nuevo.

Se consulta en **Auditoría** (`/app/{empresa}/auditoria`), detrás del permiso
`ver_costos_unidad` porque muestra montos. Es solo lectura: el rastro no se edita ni se borra,
porque un registro que se puede alterar no prueba nada.

Cada modelo declara en `$camposAuditados` lo que vale la pena seguir. Auditar todas las
columnas llena la tabla de ruido y esconde lo que importa.

> Los modelos auditados declaran también sus valores por omisión en `$attributes`. Sin eso
> Eloquent ve pasar cada default de `null` a su valor al primer `update` y lo anota como un
> cambio que nadie hizo.

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

## Probar los roles

Una instalación nueva trae solo la cuenta de Lotea. Para ejercitar los roles hay que dar de
alta un concesionario desde el panel central —que crea su casa matriz, sus diez roles y sus
categorías de costo— y luego crear usuarios desde el panel de ese cliente.

Crear un usuario con el rol **vendedor** y entrar con él es la forma más rápida de comprobar
la regla de negocio más importante del sistema: ve inventario, ventas y clientes, pero
**ningún costo ni margen**.

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

## Levantar el inventario de un patio

Un concesionario que empieza a usar Lotea normalmente **no tiene ningún control digital**: los
carros están en el patio, los precios en la cabeza del dueño y las conversaciones en WhatsApp.
No hay Excel que importar. Así que el sistema es la herramienta que hace el levantamiento.

**Levantar inventario** (`/app/{empresa}/levantamiento`) es una pantalla pensada para el
celular, para usarla caminando el patio: se elige el patio una vez, y por cada carro se lee su
documento con IA, se confirma, se pone el precio, se toman las fotos y se guarda con un botón
grande que deja el formulario listo para el siguiente. Un carro por minuto y medio contra los
cinco o seis del alta de escritorio.

Lo que se captura entra directo como **Lista** y publicado, porque son carros que ya están en
venta. Al terminar, la pantalla ofrece imprimir las etiquetas QR de todo lo capturado, así el
patio queda indexado el mismo día.

### Fichas incompletas

El VIN y el año son opcionales: en un recorrido vas a encontrar carros sin documentos a la
vista y detenerse por eso es lo que hace que nadie termine de cargar su inventario. La unidad
nace con lo que haya y queda listada en la pestaña **Por completar**, que dice exactamente qué
le falta a cada una.

Lo que **no** es negociable para publicar es el precio y al menos una foto — las de subasta
cuentan, para poder publicar preventas. El modelo despublica sola cualquier unidad que no
cumpla, por cualquier vía que se intente.

## Se puede registrar en cualquier punto del ciclo

No todos los concesionarios registran el carro cuando lo compran en subasta: hay quien lo
hace cuando ya lo tiene enfrente en el patio, y al empezar a usar el sistema **todos** cargan
de golpe el inventario que ya tenían.

Por eso el alta pregunta en qué estado entra la unidad, y no la fuerza a nacer como
*Comprada*. Se ofrecen solo los estados de inventario: nadie registra un carro que ya vendió.

Cuando el estado elegido implica que la unidad ya está en el patio, se sellan sus fechas hito
a partir de la fecha de compra, y así **sus días en inventario no arrancan en cero** — ya
llevaba tiempo ahí antes de que existiera la ficha. El historial deja constancia de que entró
directo en esa etapa, sin inventar las anteriores.

## La placa

`unidades.placa` y `unidades.tipo_placa` son **nullable a propósito**: una unidad que viene de
subasta no tiene placa guatemalteca hasta que se nacionaliza, y hasta entonces el campo vacío
es su estado normal, no un error.

El tipo se deduce de la letra inicial (`App\Enums\TipoPlaca`): de `P123ABC` sale Particular,
de `M456XYZ` Motocicleta. Importa porque el uso al que está inscrito el vehículo cambia su
precio de reventa: una placa de alquiler o comercial no vale lo mismo que una particular.

> Los códigos del enum están en un solo lugar para poder corregirlos. **Confirmalos con la
> SAT** antes de vender el sistema: los puse de memoria y no los verifiqué contra una fuente.

Se puede buscar por placa en el listado de unidades, en la búsqueda global y en el selector
de unidad de una venta — el cliente llama y dice la placa, no el VIN. **No se publica en el
portal**: no ayuda a vender y es un dato del dueño anterior.

## Automóviles, motos y pesados

Cada unidad tiene un **tipo de vehículo** y la ficha técnica cambia con él, porque una moto
no tiene puertas ni color de interior ni tracción 4x4 — y en cambio su dato principal, la
cilindrada en cc, no existía en la ficha de un carro.

| | Automóvil | Motocicleta | Camión |
|---|---|---|---|
| Puertas, color interior | Sí | **No** | Puertas sí, interior no |
| Tracción | Sí | **No** | Sí |
| Cilindrada en cc | Opcional | **Destacada** | Opcional |
| Carrocería | Sedán, SUV, pick-up… | Scooter, deportiva, naked… | Cabezal, furgón, volteo… |
| Transmisión | Automática, manual, CVT | Manual, semiautomática, automática | Automática, manual, CVT |

Las listas viven en `App\Enums\TipoVehiculo`, y el formulario, el portal público y el prompt
de la IA las leen de ahí. Al cambiar el tipo en el formulario se limpian los campos que ya no
aplican, en vez de dejarlos escondidos con un valor viejo.

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

## Llenar la ficha leyendo el documento

En el alta de una unidad hay un botón **Llenar con IA**: se suben fotos o PDF de la tarjeta
de circulación, del título americano y de la hoja del lote de subasta —hasta seis
documentos— y los campos se llenan solos. Lo que devuelve es **una propuesta**; la persona
revisa y guarda.

Los documentos se mandan **juntos en una sola petición**, no uno por uno: cada uno trae
datos distintos del mismo carro (el título tiene el VIN y el año, la hoja de subasta el daño
y el odómetro) y así el modelo puede cruzarlos. Cuando dos se contradicen, se queda con el
más formal —tarjeta, luego título, luego hoja de subasta— y **avisa la diferencia** en lugar
de esconderla.

### Es un add-on, no una función del sistema

El botón aparece **solo si el plan del cliente incluye el módulo `ia`**, no si hay llave
configurada: que falte la llave es problema del proveedor, y el cliente que paga tiene que
ver lo que paga. Si la llave falta, el botón está pero avisa que hay que llamar a soporte.

Cada plan puede llevar un tope de lecturas al mes (`planes.max_lecturas_ia`). Al alcanzarlo,
el botón avisa que se agotó el cupo y sugiere subir de plan. Las lecturas que fallan **no
consumen cupo**: el cliente no pidió que fallara.

Poné tu llave de [OpenRouter](https://openrouter.ai/keys) en el `.env`:

```
OPENROUTER_API_KEY=sk-or-v1-...
OPENROUTER_MODELO=qwen/qwen2.5-vl-72b-instruct
OPENROUTER_PRECIO_ENTRADA=0.25
OPENROUTER_PRECIO_SALIDA=0.75
```

### Consumo y costo

Cada lectura queda registrada en `lecturas_ia` con los tokens que reportó OpenRouter y el
costo calculado a partir de ellos — no es un estimado. En el panel central se ve el consumo
del mes por cliente y el costo por lectura, que es lo único que hace falta para saber si el
precio del add-on deja margen.

Lo que devuelve el modelo pasa por `App\Services\ValidadorDeDatosLeidos` antes de tocar el
formulario: un VIN que no tenga 17 caracteres válidos se descarta, un año 2045 se descarta,
y una transmisión que no esté en nuestra lista se descarta. La IA se equivoca y a veces
inventa; un VIN mal puesto sigue al carro toda su vida.

El documento se borra del servidor apenas se lee. Las marcas y líneas que no existan en el
catálogo se crean solas, porque es preferible un catálogo que crece a una ficha incompleta.

## Estado

Terminado: fundación y tenancy, catálogos, la unidad con su ciclo de vida, el tablero del
patio, el costeo con multimoneda y prorrateo, el portal público con CRM, clientes y ventas
con comisiones, empleados, caja por sucursal, taller y cartera de crédito propio.

Del plan original queda pendiente: nómina de Guatemala, inversionistas por unidad,
inventario de repuestos, reportes avanzados y el "qué comprar".
