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
        $hex = ltrim($hex ?: $this->color_de_marca, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $r = (int) hexdec(substr($hex, 0, 2));
        $v = (int) hexdec(substr($hex, 2, 2));
        $a = (int) hexdec(substr($hex, 4, 2));

        // Luminancia percibida: el verde pesa mucho más que el azul.
        $luminancia = (0.2126 * $r + 0.7152 * $v + 0.0722 * $a) / 255;

        return $luminancia < 0.55 ? $this->logo_oscuro_url : $this->logo_url;
    }

    /** El símbolo solo, sin el nombre. Para espacios pequeños y cuadrados. */
    public function getIsotipoUrlAttribute(): ?string
    {
        return $this->archivoDeMarca($this->isotipo_path);
    }

    public function getFaviconUrlAttribute(): ?string
    {
        return $this->archivoDeMarca($this->favicon_path);
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
    protected function archivoDeMarca(?string $ruta): ?string
    {
        if (blank($ruta) || ! AlmacenDeArchivos::disco()->exists($ruta)) {
            return null;
        }

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
