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
| `LOTEA_OPERADOR_EMAIL` | `admin@lotea.gt` |
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

Crea la carpeta raíz si falta, escribe un archivo, lo lee, lo borra y dice cuánto tardó cada
paso. Es lo primero que hay que correr al configurar un servidor nuevo.

> Flysystem no crea su propio `root`. Si `FTP_ROOT` apunta a una carpeta que no existe, la
> primera subida falla con «creating parent directory failed» y no hay forma de adivinar por
> qué. Por eso el comando la crea.

Las conversiones (`miniatura`, `web`) se generan **al subir la foto**, no en una cola. El portal
muestra la conversión `web`: si se encolara y nadie levantara un worker, el catálogo saldría con
las tarjetas rotas sin ningún error visible. Cuesta medio segundo por foto y ahorra montar
supervisor. Con un worker corriendo se puede poner `MEDIA_CONVERSIONES_EN_COLA=true`.

Todo cuelga de la carpeta que diga `FTP_ROOT`, que por defecto es `/SAS-LOTEA`. Importa
ponerla: si el FTP se comparte con otros sistemas, sin una raíz propia Lotea les llenaría la
suya de carpetas sueltas, porque medialibrary guarda en directorios numerados.

Dentro, los archivos se ordenan por concesionario y unidad:

```
SAS-LOTEA/
├── marcas/                                   ← logos y favicons
└── autos-del-valle/
    └── unidades/
        └── 12/
            ├── fotos/37/frente.jpg
            │   └── conversions/frente-web.webp
            ├── fotos-subasta/38/lote.jpg
            └── documentos/40/titulo.pdf
```

Se puede abrir con cualquier cliente de FTP y entender qué hay, y si un cliente se da de baja
su carpeta se borra entera. Los números son ids, no el stock del carro: el stock se puede
editar y las rutas ya guardadas quedarían apuntando a la nada.

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

### Las versiones del logo del cliente

```bash
php artisan lotea:variantes-logo autos-del-valle
php artisan lotea:variantes-logo --forzar      # rehace todas, de todos
```

El cliente entrega el logo que usa en Facebook, con su fondo pegado. De ese
archivo salen ocho versiones —sin inventar nada: se recorta, se quita el fondo y
se oscurece lo blanco respetando los colores de la marca:

| Variante | Qué es | Dónde se usa |
|---|---|---|
| `isologo` | La marca completa, sin fondo | Panel en modo oscuro |
| `isologo-claro` | Igual, con lo blanco oscurecido | Portal y papel |
| `isotipo` | Solo el símbolo | — |
| `isotipo-claro` | El símbolo en oscuro | Centro del QR |
| `isotipo-cuadrado(-claro)` | El símbolo encuadrado | Favicon |
| `logotipo(-claro)` | Solo el nombre escrito | Libre |

Las que el sistema usa solo se asignan si el campo está vacío: lo que el cliente
subió a mano manda, salvo que se pase `--forzar`. El mismo proceso corre solo
cuando el concesionario sube su logo desde **Mi marca y contacto**, donde cada
casilla dice en qué parte del sistema se ve esa imagen —la barra del portal, la
portada, el centro del QR— para que nadie suba el archivo equivocado.

Ahí mismo se sube la **imagen de portada**: el fondo de la cabecera grande del
portal. Lleva una capa oscura encima porque sin ella el titular blanco se pierde
sobre una foto clara, y eso es peor que no tener foto.

> Del símbolo y del favicon se usa la versión **clara** —la de trazo oscuro—
> porque van sobre blanco: el cuadro del QR y la pestaña del navegador. La
> plateada sobre blanco se ve desvaída.

### Cambiar de disco con archivos ya subidos

```bash
php artisan lotea:migrar-archivos --fingir   # qué movería
php artisan lotea:migrar-archivos            # moverlo
```

Cambiar `LOTEA_DISCO_ARCHIVOS` solo dice dónde se guarda **de ahora en adelante**. Lo que ya
estaba subido se queda donde estaba, el sistema lo busca en el disco nuevo y no lo encuentra: la
ficha se ve sin fotos y el portal muestra las tarjetas vacías. Este comando lo pasa al disco
configurado y regenera las conversiones.

### Límites de subida

`post_max_size` tiene que ser **mayor o igual** que `upload_max_filesize`, y los dos por encima
de lo que acepten los formularios. Si un archivo pasa de `post_max_size`, PHP descarta la
petición entera antes de que Laravel la vea: no hay error que mostrar, el formulario se queda
igual y el usuario cree que el sistema no hace nada.

```ini
upload_max_filesize = 12M
post_max_size = 12M
```

Las imágenes se encogen en el navegador antes de subirse —a 1920 px las fotos y la portada, a
1200 px los logos— así que una foto de celular de 8 MB llega como medio mega. Los `maxSize` de
los formularios están por debajo del límite del servidor a propósito: así el rechazo lo hace el
formulario, con un mensaje que se entiende.

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

## Subir fotos sin que se caiga

Guardar una unidad con fotos parece barato y no lo es. Por cada foto hay que
subir el original, generar dos versiones —miniatura y web— y subir cada una.
Con ocho fotos son más de treinta viajes al almacenamiento y dieciséis imágenes
procesadas. Heroku corta el request a los treinta segundos, así que eso **no
cabe** en la petición web, y cuando revienta la unidad ya quedó creada pero el
guardado murió.

Por eso las conversiones van a la cola, y **eso exige el dyno `worker`
corriendo**:

```bash
heroku ps:scale worker=1 -a app-lotea-prd
```

Sin él, las fotos se suben pero nunca aparecen sus miniaturas. Si por costo se
prefiere no tenerlo, hay que poner `MEDIA_CONVERSIONES_EN_COLA=false` y asumir
que subir muchas fotos de golpe puede caerse.

Nada de esto tiene que ver con la base de datos: guardar una unidad son unas
pocas consultas de milisegundos. El tiempo se va en imágenes y en red.

### Los límites, que fallan en silencio

Tres números tienen que cuadrar: el del formulario (`LimiteDeSubida`), los de
PHP (`.user.ini`) y el de Livewire (`config/livewire.php`). Si el archivo supera
`post_max_size`, **PHP descarta la petición entera antes de que Laravel
arranque**: sin excepción, sin log, el botón no hace nada. Hay tests que fallan
si esos números se desalinean, porque este error no se descubre mirando.

El `.user.ini` importa especialmente en Heroku, que deja PHP en 2 MB por
archivo. Una foto de teléfono pesa entre 4 y 12 MB.

**Va en `public/`, no en la raíz.** PHP lee ese archivo del directorio del script
en ejecución, que es el document root; puesto en la raíz del proyecto se queda
ahí sin que nadie lo mire, y los límites que declara no se aplican. Hay un test
que falla si aparece uno en la raíz, porque desde fuera se ve idéntico.

Cuando el límite se queda corto, PHP descarta el archivo pero deja seguir la
petición: la pantalla marca la foto en verde y el archivo nunca existió. Al
guardar sale un error de Flysystem diciendo que no puede leer algo de
`livewire-tmp/`. Si aparece ese error, el primer sitio donde mirar es este.

## Las etiquetas del parabrisas

La hoja se puede sacar de dos formas y las dos existen por una razón:

- **Imprimir** manda la página a la impresora del navegador. Es un clic menos,
  pero pasa por cuatro manos —el CSS, el navegador, el sistema operativo y el
  driver— y basta que una falle para que salga una hoja en blanco sin decir por
  qué. Pasó en Windows.
- **Descargar PDF** arma la hoja en el servidor. Sale igual en Windows, en Mac y
  en un teléfono, y se puede guardar y reimprimir.

Cada salida lleva el código en el formato que su motor sabe dibujar, y no es el
mismo: el navegador imprime bien un SVG escrito dentro de la página y falla con
uno metido en un `<img>`; dompdf hace exactamente lo contrario. Está probado en
los dos sentidos, así que no se unifiquen sin comprobarlo.

El logo del concesionario va sobre un rectángulo blanco y no directo sobre la
banda de color: su logo puede llevar ese mismo color dentro y esa parte
desaparece. Pasó — se leía «RTADORA» en vez de «IMPORTADORA».

## Pasar un concesionario a producción

Cuando un cliente se arma probando en una máquina y hay que llevarlo tal cual a
producción, `ClienteInicialSeeder` lo hace. Los datos **no van en el código**:
el nombre de un concesionario, sus correos, sus VIN y sus precios son suyos, y
esto es un repositorio público.

Se leen, en este orden:

1. La variable de entorno `SEMILLA_CLIENTE`, con el JSON completo. Es la vía
   para Heroku, donde no se puede dejar un archivo suelto.
2. `database/seeders/datos/cliente.json`, que está en `.gitignore`.

La forma exacta está en `database/seeders/datos/cliente.ejemplo.json`, que sí va
al repositorio porque no tiene datos de nadie. Un test comprueba que ese ejemplo
se pueda sembrar tal cual, para que no se quede viejo, y otro que no vuelvan a
entrar correos de personas reales.

Las contraseñas nunca van en el archivo: cada usuario dice de qué variable de
entorno sale la suya, y sin ella queda una temporal que se avisa en pantalla.

```bash
php artisan db:seed --class=ClienteInicialSeeder
```

Es idempotente: correrlo dos veces no duplica nada.

## Entrar al panel de un cliente

La cuenta de Lotea no pertenece a la empresa de ningún concesionario —ni debe: contaría como
usuario suyo, saldría en su lista y gastaría cupo de su plan— así que Filament responde 404 al
abrir su panel. El botón **«Entrar a dar soporte»** del panel central abre esa puerta:

- Solo para quien tenga `users.es_operador`. Se comprueba contra la base en cada request, así que
  quitar la bandera corta el acceso al instante.
- Se entra **como uno mismo**, no como el cliente: lo que se toque queda a nombre de quien lo
  tocó. Si mañana el cliente reclama que le cambiaron algo, el rastro dice la verdad.
- Entrada y salida quedan anotadas en el historial de ese concesionario, que el dueño ve en su
  propia pantalla de auditoría.
- Una barra ámbar arriba avisa dónde se está. El riesgo no es entrar: es olvidarse de que se
  entró y creer que se está en el panel propio.
- Es de una empresa a la vez, no se entra a un concesionario suspendido, y al salir se cierra.

## Los roles y sus permisos

Cada concesionario nace con diez roles ya configurados —`App\Support\PermisosPorRol` tiene el
mapa— para que su gente pueda trabajar el primer día sin que nadie arme permisos a mano. El
dueño ajusta lo que quiera desde la pantalla de Roles.

```bash
php artisan lotea:permisos-por-rol autos-del-valle   # los clientes de antes
php artisan lotea:permisos-por-rol --forzar          # rehace también los ya configurados
```

Sin `--forzar` solo llena los roles vacíos: nadie quiere que un comando le deshaga lo que
estuvo ajustando.

> **Ver costos y márgenes** y **ver el precio mínimo** los tienen solo el dueño, el gerente,
> quien compra, quien coordina la importación, el taller y el contador. El vendedor **nunca**:
> si sabe lo que costó el carro, el cliente lo sabrá en la siguiente hora y el margen se negocia
> desde ahí. Hay tests que fallan si algún rol se lleva ese permiso por descuido.

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

## Licencia

Software propietario. © 2026 Jefferson Juárez. Todos los derechos reservados.

El código está a la vista para mostrar cómo está construido; eso no concede permiso para
usarlo, copiarlo ni desplegarlo. Para licenciarlo, hablemos.
