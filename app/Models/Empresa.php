<?php

namespace App\Models;

use App\Http\Controllers\MarcaController;
use App\Models\Scopes\EmpresaScope;
use App\Support\AlmacenDeArchivos;
use App\Support\AvatarDeIniciales;
use App\Support\WhatsApp;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * El tenant. Cada empresa es un concesionario cliente de Lotea y no ve
 * absolutamente nada de las demás.
 *
 * Este modelo es de los poquísimos que NO usa PerteneceAEmpresa: es la raíz.
 */
class Empresa extends Model implements HasName
{
    use HasFactory, SoftDeletes;

    /** El ámbar de Lotea, para quien no eligió color propio. */
    public const COLOR_POR_DEFECTO = '#f59e0b';

    protected $table = 'empresas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'fecha_activacion' => 'date',
            'fecha_vencimiento' => 'date',
            'suspendida_en' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function cobros(): HasMany
    {
        return $this->hasMany(Cobro::class);
    }

    public function unidades(): HasMany
    {
        return $this->sinScopeDeEmpresa($this->hasMany(Unidad::class));
    }

    public function lecturasIa(): HasMany
    {
        return $this->sinScopeDeEmpresa($this->hasMany(LecturaIa::class));
    }

    /**
     * Quita el filtro por empresa activa de una relación de esta empresa.
     *
     * La relación ya acota por empresa_id, así que el scope encima no agrega
     * seguridad: solo hace que `$otraEmpresa->unidades` devuelva vacío cuando
     * el contexto activo es distinto, que es exactamente lo que necesita el
     * panel central para ver a todos los clientes.
     */
    protected function sinScopeDeEmpresa(HasMany $relacion): HasMany
    {
        return $relacion->withoutGlobalScope(EmpresaScope::class);
    }

    /** ¿Este cliente contrató el módulo? */
    public function tieneModulo(string $modulo): bool
    {
        return (bool) $this->plan?->permite($modulo);
    }

    public function lecturasIaDelMes(?string $periodo = null): int
    {
        return $this->lecturasIa()->exitosas()->delMes($periodo)->count();
    }

    public function costoIaDelMes(?string $periodo = null): float
    {
        return (float) $this->lecturasIa()->delMes($periodo)->sum('costo_usd');
    }

    /** Le queda cupo este mes, o el plan no tiene tope. */
    public function puedeLeerConIa(): bool
    {
        if (! $this->tieneModulo('ia')) {
            return false;
        }

        $tope = $this->plan?->max_lecturas_ia;

        return $tope === null || $this->lecturasIaDelMes() < $tope;
    }

    public function estaSuspendida(): bool
    {
        return $this->suspendida_en !== null;
    }

    /** Puede operar: ni dada de baja ni suspendida por falta de pago. */
    public function puedeOperar(): bool
    {
        return $this->activa && ! $this->estaSuspendida();
    }

    public function getEstadoSuscripcionAttribute(): string
    {
        return match (true) {
            ! $this->activa => 'baja',
            $this->estaSuspendida() => 'suspendida',
            default => 'activa',
        };
    }

    /** Lo que factura al mes. Es la unidad del MRR. */
    public function getMensualidadAttribute(): float
    {
        return $this->puedeOperar() ? (float) ($this->plan?->precio_mensual ?? 0) : 0.0;
    }

    /** Lo que se ve en el selector de empresa del panel. */
    public function getFilamentName(): string
    {
        return $this->nombre_comercial ?: $this->nombre;
    }

    /**
     * El color con el que se pinta su panel y su portal.
     *
     * Se valida aquí porque de este hex sale la paleta del panel: un valor con
     * basura no se puede convertir y dejaría al cliente sin panel.
     */
    public function getColorDeMarcaAttribute(): string
    {
        return is_string($this->color_primario)
            && preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $this->color_primario)
                ? $this->color_primario
                : self::COLOR_POR_DEFECTO;
    }

    /**
     * El logo para fondos claros: el portal, las etiquetas, el panel de día.
     *
     * Se prefiere la versión clara —trazo oscuro, sin fondo— porque el archivo
     * que sube el cliente casi siempre trae su propio fondo negro pegado, y
     * sobre una página blanca eso es un recuadro oscuro en medio de nada.
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->archivoDeMarca($this->logo_claro_path)
            ?? $this->archivoDeMarca($this->logo_path);
    }

    /** El mismo logo sobre fondo oscuro: el panel de noche, el hero del portal. */
    public function getLogoOscuroUrlAttribute(): ?string
    {
        return $this->archivoDeMarca($this->logo_oscuro_path) ?? $this->logo_url;
    }

    /**
     * El logo que corresponde a un fondo de color cualquiera.
     *
     * La cabecera de la etiqueta va pintada con el color de la marca, y ese
     * color puede ser un rojo oscuro o un amarillo claro: en uno hace falta el
     * logo de trazo claro y en el otro el de trazo oscuro.
     */
    public function logoParaFondo(?string $hex = null): ?string
    {
        return $this->esFondoOscuro($hex) ? $this->logo_oscuro_url : $this->logo_url;
    }

    /**
     * El mismo logo, pero escrito dentro del documento.
     *
     * Un PDF se arma en el servidor y no puede salir a pedir una imagen a
     * ninguna URL, así que ahí el logo tiene que ir incrustado o no va.
     */
    public function logoIncrustadoParaFondo(?string $hex = null): ?string
    {
        $campos = $this->esFondoOscuro($hex)
            ? ['logo_oscuro_path', 'logo_claro_path', 'logo_path']
            : ['logo_claro_path', 'logo_path', 'logo_oscuro_path'];

        foreach ($campos as $campo) {
            $archivo = $this->archivoDeMarcaLocal($campo);

            if ($archivo === null) {
                continue;
            }

            $tipo = mime_content_type($archivo) ?: 'image/png';

            return 'data:'.$tipo.';base64,'.base64_encode((string) file_get_contents($archivo));
        }

        return null;
    }

    /** Luminancia percibida: el verde pesa mucho más que el azul. */
    protected function esFondoOscuro(?string $hex = null): bool
    {
        [$r, $v, $a] = $this->aRgb($hex ?: $this->color_de_marca);

        return (0.2126 * $r + 0.7152 * $v + 0.0722 * $a) / 255 < 0.55;
    }

    /** El símbolo solo, sin el nombre. Para espacios pequeños y cuadrados. */
    public function getIsotipoUrlAttribute(): ?string
    {
        return $this->archivoDeMarca($this->isotipo_path);
    }

    /** La foto de fondo de la portada del portal, si el cliente puso una. */
    public function getPortadaUrlAttribute(): ?string
    {
        return $this->archivoDeMarca($this->portada_path);
    }

    public function getFaviconUrlAttribute(): ?string
    {
        return $this->archivoDeMarca($this->favicon_path);
    }

    /**
     * El icono de la pestaña, dibujado como SVG.
     *
     * Las dos letras del concesionario sobre su color, como el icono de una
     * aplicación. No es capricho: a 16 píxeles —el tamaño real de una pestaña—
     * un logo apaisado de línea fina se convierte en una mancha o desaparece,
     * mientras que dos letras se leen siempre.
     *
     * En SVG y no en PNG por dos razones: se ve nítido en cualquier pantalla y
     * no hace falta una fuente instalada en el servidor para dibujarlo.
     */
    public function getFaviconSvgAttribute(): string
    {
        $fondo = $this->color_de_marca;
        $tinta = $this->tintaSobre($fondo);
        $letras = e($this->iniciales);

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
                <rect width="64" height="64" rx="14" fill="{$fondo}"/>
                <text x="32" y="33" fill="{$tinta}" text-anchor="middle" dominant-baseline="central"
                      font-family="system-ui, -apple-system, Segoe UI, Roboto, sans-serif"
                      font-size="30" font-weight="700" letter-spacing="-1">{$letras}</text>
            </svg>
            SVG;
    }

    /** La dirección del icono de la pestaña. */
    public function getFaviconPestanaUrlAttribute(): string
    {
        $url = parse_url(route('marca', ['slug' => $this->slug, 'tipo' => 'pestana']), PHP_URL_PATH);

        return $url.'?v='.substr(md5($this->color_de_marca.$this->getFilamentName()), 0, 8);
    }

    /** Blanco o casi negro, el que se lea sobre ese fondo. */
    protected function tintaSobre(string $hex): string
    {
        [$r, $v, $a] = $this->aRgb($hex);

        return (0.2126 * $r + 0.7152 * $v + 0.0722 * $a) / 255 < 0.55 ? '#ffffff' : '#111827';
    }

    /** @return array{int, int, int} */
    protected function aRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /** El WhatsApp del concesionario, listo para un href. */
    public function getWhatsappEnlaceAttribute(): ?string
    {
        return WhatsApp::enlace($this->whatsapp ?: $this->telefono);
    }

    /**
     * Las redes que el concesionario llenó, para pintarlas en el portal.
     *
     * Se guarda lo que el cliente pegue —a veces el usuario, a veces la URL
     * entera— y aquí se arma el enlace bueno en los dos casos.
     *
     * @return array<int, array{red: string, nombre: string, url: string}>
     */
    public function getRedesAttribute(): array
    {
        $bases = [
            'facebook' => ['Facebook', 'https://facebook.com/'],
            'instagram' => ['Instagram', 'https://instagram.com/'],
            'tiktok' => ['TikTok', 'https://tiktok.com/@'],
            'youtube' => ['YouTube', 'https://youtube.com/@'],
        ];

        $redes = [];

        foreach ($bases as $campo => [$nombre, $base]) {
            $valor = trim((string) $this->{$campo});

            if (blank($valor)) {
                continue;
            }

            $redes[] = [
                'red' => $campo,
                'nombre' => $nombre,
                'url' => str_starts_with($valor, 'http')
                    ? $valor
                    : $base.ltrim($valor, '@/'),
            ];
        }

        return $redes;
    }

    /** Las dos letras que se muestran cuando no hay logo. */
    public function getInicialesAttribute(): string
    {
        return AvatarDeIniciales::de($this->getFilamentName());
    }

    /**
     * La URL de un archivo de marca, relativa al dominio que se esté sirviendo.
     *
     * Relativa a propósito. El portal del cliente corre en su propio dominio, y
     * una URL absoluta pondría «lotea» en el src del logo del cliente, que es
     * exactamente lo que la marca blanca no debe hacer. De paso funciona igual
     * en cualquier host y puerto.
     *
     * Se comprueba que el archivo esté: uno borrado a mano dejaría una imagen
     * rota en todas las pantallas del cliente.
     */
    /**
     * Las URL de marca ya resueltas en este request.
     *
     * Cada resolución pregunta al disco si el archivo está, y en producción ese
     * disco es un FTP: un viaje a otro servidor. La hoja de etiquetas pedía el
     * logo dos veces por etiqueta, así que con cuarenta unidades eran más de
     * cien viajes y la página tardaba lo que tardara la red.
     *
     * @var array<string, ?string>
     */
    protected array $urlesDeMarca = [];

    protected function archivoDeMarca(?string $ruta): ?string
    {
        if (blank($ruta)) {
            return null;
        }

        // La versión entra en la clave: al guardar una imagen nueva cambia el
        // updated_at y esto se resuelve otra vez en lugar de servir lo viejo.
        $clave = $ruta.'|'.$this->updated_at?->timestamp;

        if (array_key_exists($clave, $this->urlesDeMarca)) {
            return $this->urlesDeMarca[$clave];
        }

        return $this->urlesDeMarca[$clave] = $this->resolverArchivoDeMarca($ruta);
    }

    /**
     * La URL, sin preguntarle al almacenamiento si el archivo está.
     *
     * Antes se comprobaba, y esa comprobación es un viaje de red: contra R2
     * cuesta entre 250 y 375 ms. El encabezado del portal pide el logo y el
     * icono, así que eran tres o cuatro viajes —más de un segundo— antes de
     * mandar el primer byte, en cada visita. Con dos vehículos en el catálogo.
     *
     * El camino guardado en la base es la fuente de verdad: si está, el archivo
     * se subió. Si alguien lo borró por fuera, sale una imagen rota, y eso es
     * mejor que un segundo de espera por página para todos los demás.
     */
    protected function resolverArchivoDeMarca(string $ruta): ?string
    {

        // En un disco sin URL pública —el FTP— el archivo se pide a la ruta que
        // lo sirve, igual que las fotos de las unidades.
        if (! AlmacenDeArchivos::esLocalPublico()) {
            $tipo = array_search($this->campoDeMarca($ruta), MarcaController::TIPOS, true);
            $url = parse_url(route('marca', ['slug' => $this->slug, 'tipo' => $tipo]), PHP_URL_PATH);

            return $url.'?v='.$this->versionDeMarca($ruta);
        }

        return parse_url(AlmacenDeArchivos::disco()->url($ruta), PHP_URL_PATH)
            .'?v='.$this->versionDeMarca($ruta);
    }

    /**
     * Un sello que cambia cuando cambia el archivo.
     *
     * La URL de la marca es siempre la misma —/marca/{slug}/logo— y se sirve con
     * una semana de caché. Sin esto, un cliente que cambia su logo lo seguiría
     * viendo viejo en su portal, y sus visitantes también, hasta que caducara.
     */
    protected function versionDeMarca(string $ruta): string
    {
        return substr(md5($ruta.$this->updated_at?->timestamp), 0, 8);
    }

    /** Qué campo de marca corresponde a esta ruta guardada. */
    protected function campoDeMarca(string $ruta): string
    {
        foreach (MarcaController::TIPOS as $campo) {
            if ($this->{$campo} === $ruta) {
                return $campo;
            }
        }

        return 'logo_path';
    }

    /**
     * El archivo de marca en disco local, listo para entregar.
     *
     * Lo usa MarcaController: si vive en el FTP, deja copia local en la primera
     * lectura como hacen las fotos.
     */
    public function archivoDeMarcaLocal(string $campo): ?string
    {
        $ruta = $this->{$campo};

        if (blank($ruta) || ! AlmacenDeArchivos::disco()->exists($ruta)) {
            return null;
        }

        return AlmacenDeArchivos::archivoLocalDeRuta($ruta);
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function sucursales(): HasMany
    {
        return $this->sinScopeDeEmpresa($this->hasMany(Sucursal::class));
    }

    public function categoriasCosto(): HasMany
    {
        return $this->sinScopeDeEmpresa($this->hasMany(CategoriaCosto::class));
    }

    public function proveedores(): HasMany
    {
        return $this->sinScopeDeEmpresa($this->hasMany(Proveedor::class));
    }
}
