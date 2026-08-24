<?php

namespace App\Models;

use App\Enums\EstadoUnidad;
use App\Enums\TipoPlaca;
use App\Enums\TipoVehiculo;
use App\Models\Concerns\DejaRastro;
use App\Models\Concerns\PerteneceAEmpresa;
use App\Support\CodigoDeUnidad;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Unidad extends Model implements HasMedia
{
    use DejaRastro, HasFactory, InteractsWithMedia, PerteneceAEmpresa, SoftDeletes;

    protected $table = 'unidades';

    protected $guarded = ['id'];

    /** Lo que se sigue en el rastro: lo que mueve plata o cambia el negocio. */
    protected array $camposAuditados = ['estado', 'precio_lista', 'precio_minimo', 'costo_total', 'publicado', 'vin', 'placa'];

    /**
     * Los valores por omisión, declarados también acá.
     *
     * La base los tiene, pero el modelo no los conocía: al primer update
     * Eloquent los veía pasar de null a su default y los anotaba como cambios
     * reales, ensuciando el rastro de auditoría con movimientos que nadie hizo.
     */
    protected $attributes = [
        'tipo_vehiculo' => 'automovil',
        'estado' => 'comprada',
        'odometro_unidad' => 'mi',
        'tiene_llaves' => true,
        'publicado' => false,
        'destacado' => false,
        'costo_total' => 0,
        'costo_presupuestado' => 0,
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoUnidad::class,
            'tipo_vehiculo' => TipoVehiculo::class,
            'tipo_placa' => TipoPlaca::class,
            'estado_desde' => 'datetime',
            'fecha_compra' => 'date',
            'fecha_recepcion' => 'date',
            'fecha_lista' => 'date',
            'fecha_venta' => 'date',
            'precio_lista' => 'decimal:2',
            'precio_minimo' => 'decimal:2',
            'costo_total' => 'decimal:2',
            'costo_presupuestado' => 'decimal:2',
            'publicado' => 'boolean',
            'destacado' => 'boolean',
            'tiene_llaves' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Unidad $unidad) {
            $unidad->codigo_qr ??= CodigoDeUnidad::generar();
        });

        // El slug se arma cuando el carro está por salir al portal y no antes:
        // recién comprado no tiene marca ni línea, y saldría un slug feo que ya
        // no se puede cambiar sin romper el enlace que alguien compartió.
        static::saving(function (Unidad $unidad) {
            if ($unidad->publicado && blank($unidad->slug)) {
                $unidad->slug = $unidad->generarSlug();
            }
        });

        // Vale para cualquier camino —formulario, importación, un script— y no
        // solo para la pantalla que lo pide.
        static::saving(function (Unidad $unidad) {
            if ($unidad->publicado && ! $unidad->puedePublicarse()) {
                $unidad->publicado = false;
            }
        });
    }

    /**
     * Tres colecciones distintas a propósito.
     *
     * Las fotos de subasta son la prueba de cómo venía el carro: cuando llega
     * al patio con un daño que no estaba en el anuncio, esa comparación es la
     * diferencia entre reclamar y comerse la pérdida.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('fotos_subasta');
        $this->addMediaCollection('fotos');
        $this->addMediaCollection('documentos');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('miniatura')
            ->width(320)
            ->height(240)
            ->format('webp')
            ->nonQueued();

        // 30 fotos por carro × 40 carros × N clientes: el peso del almacenamiento
        // es un costo real del negocio, no un detalle técnico.
        $this->addMediaConversion('web')
            ->width(1400)
            ->format('webp')
            ->performOnCollections('fotos', 'fotos_subasta');
    }

    public function getFotoPrincipalAttribute(): ?string
    {
        return $this->getFirstMediaUrl('fotos', 'miniatura') ?: null;
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function linea(): BelongsTo
    {
        return $this->belongsTo(Linea::class);
    }

    public function transiciones(): HasMany
    {
        return $this->hasMany(UnidadTransicion::class)->latest('ocurrio_en');
    }

    public function costos(): HasMany
    {
        return $this->hasMany(CostoUnidad::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function ordenesTrabajo(): HasMany
    {
        return $this->hasMany(OrdenTrabajo::class);
    }

    /** La venta que efectivamente cerró, si ya se vendió. */
    public function venta(): HasOne
    {
        return $this->hasOne(Venta::class)->where('estado', 'cerrada')->whereNull('anulada_en');
    }

    /** Cómo se nombra el carro en pantalla: "Toyota RAV4 2019". */
    /**
     * La parte legible de la URL del carro en el portal.
     *
     * Lleva el stock al final para que sea único dentro del concesionario: dos
     * Yaris 2018 son dos carros distintos y cada uno necesita su enlace.
     *
     * Una vez puesto no se vuelve a tocar aunque cambien la marca o el año: ese
     * enlace ya salió por WhatsApp y tiene que seguir abriendo.
     */
    public function generarSlug(): string
    {
        $base = str($this->descripcion)->slug()->value();

        return str(filled($base) ? "{$base}-{$this->stock_no}" : "unidad-{$this->stock_no}")
            ->slug()
            ->value();
    }

    public function getDescripcionAttribute(): string
    {
        return collect([
            $this->marca?->nombre,
            $this->linea?->nombre,
            $this->version,
            $this->anio,
        ])->filter()->implode(' ');
    }

    /** Días desde que se compró. El capital dormido se mide desde aquí. */
    public function getDiasInventarioAttribute(): ?int
    {
        if (! $this->fecha_compra) {
            return null;
        }

        $hasta = $this->fecha_venta ?? now();

        return (int) $this->fecha_compra->diffInDays($hasta);
    }

    /** Días que lleva parada en la etapa actual: el semáforo del kanban. */
    public function getDiasEnEstadoAttribute(): ?int
    {
        return $this->estado_desde
            ? (int) $this->estado_desde->diffInDays(now())
            : null;
    }

    /**
     * El precio con el que se calcula la utilidad: el real si ya se vendió, el
     * de lista mientras tanto. Un margen sobre precio de lista es una
     * aspiración; sobre el precio de cierre es un hecho.
     */
    public function getPrecioParaMargenAttribute(): ?string
    {
        return $this->venta?->precio_final ?? $this->precio_lista;
    }

    public function getUtilidadEstimadaAttribute(): ?string
    {
        if ($this->precio_para_margen === null) {
            return null;
        }

        return bcsub((string) $this->precio_para_margen, (string) $this->costo_total, 2);
    }

    /**
     * Lo que le falta a la ficha para estar cerrada.
     *
     * En un levantamiento de inventario se captura lo que se puede y se
     * completa después; esta lista es la que guía ese trabajo pendiente.
     *
     * @return array<int, string>
     */
    public function loQueFalta(): array
    {
        $falta = [];

        if (blank($this->vin)) {
            $falta[] = 'VIN';
        }

        if (blank($this->anio)) {
            $falta[] = 'año';
        }

        if (blank($this->marca_id)) {
            $falta[] = 'marca';
        }

        if (blank($this->precio_lista)) {
            $falta[] = 'precio';
        }

        if (! $this->tieneAlgunaFoto()) {
            $falta[] = 'fotos';
        }

        return $falta;
    }

    public function estaCompleta(): bool
    {
        return $this->loQueFalta() === [];
    }

    /**
     * Publicar un carro sin foto ni precio le hace daño a la imagen del
     * concesionario: el cliente entra a la ficha y no encuentra lo único que
     * fue a buscar.
     */
    public function puedePublicarse(): bool
    {
        return filled($this->precio_lista) && $this->tieneAlgunaFoto();
    }

    /**
     * Las de subasta cuentan: un carro en preventa todavía no tiene fotos del
     * patio y publicarlo con las del anuncio es mejor que sin ninguna.
     */
    public function tieneAlgunaFoto(): bool
    {
        return $this->getMedia('fotos')->isNotEmpty()
            || $this->getMedia('fotos_subasta')->isNotEmpty();
    }

    /** @return array<int, string> */
    public function loQueFaltaParaPublicar(): array
    {
        return array_values(array_intersect($this->loQueFalta(), ['precio', 'fotos']));
    }

    public function tienePlaca(): bool
    {
        return filled($this->placa);
    }

    public function esMoto(): bool
    {
        return $this->tipo_vehiculo === TipoVehiculo::Motocicleta;
    }

    /**
     * Fichas a medio llenar.
     *
     * Se filtra en SQL lo que se puede (VIN, año, marca, precio) y las fotos
     * se revisan con una subconsulta a media, para no traer todo a memoria.
     */
    public function scopeIncompletas($query)
    {
        return $query->where(fn ($q) => $q
            ->whereNull('vin')
            ->orWhereNull('anio')
            ->orWhereNull('marca_id')
            ->orWhereNull('precio_lista')
            ->orWhereDoesntHave('media', fn ($m) => $m->whereIn('collection_name', ['fotos', 'fotos_subasta'])));
    }

    public function scopeEnInventario($query)
    {
        return $query->whereIn(
            'estado',
            collect(EstadoUnidad::cases())->filter->esInventario()->map->value->all(),
        );
    }

    public function scopePublicables($query)
    {
        return $query->whereIn(
            'estado',
            collect(EstadoUnidad::cases())->filter->admitePreventa()->map->value->all(),
        );
    }
}
