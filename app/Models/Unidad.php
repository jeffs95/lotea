<?php

namespace App\Models;

use App\Enums\EstadoUnidad;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Unidad extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, PerteneceAEmpresa, SoftDeletes;

    protected $table = 'unidades';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoUnidad::class,
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

    /** Cómo se nombra el carro en pantalla: "Toyota RAV4 2019". */
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

    public function getUtilidadEstimadaAttribute(): ?string
    {
        if ($this->precio_lista === null) {
            return null;
        }

        return bcsub((string) $this->precio_lista, (string) $this->costo_total, 2);
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
