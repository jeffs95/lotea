<?php

namespace App\Models;

use App\Enums\EstadoUnidad;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unidad extends Model
{
    use HasFactory, PerteneceAEmpresa, SoftDeletes;

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
