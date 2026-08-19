<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostoUnidad extends Model
{
    use HasFactory, PerteneceAEmpresa;

    protected $table = 'costos_unidad';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
            'tipo_cambio' => 'decimal:6',
            'monto_base' => 'decimal:2',
            'es_presupuesto' => 'boolean',
            'anulado_en' => 'datetime',
        ];
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaCosto::class, 'categoria_costo_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function gastoCompartido(): BelongsTo
    {
        return $this->belongsTo(GastoCompartido::class, 'prorrateado_de_id');
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_en !== null;
    }

    public function vieneDeProrrateo(): bool
    {
        return $this->prorrateado_de_id !== null;
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('anulado_en');
    }

    public function scopeReales(Builder $query): Builder
    {
        return $query->where('es_presupuesto', false);
    }

    public function scopePresupuestados(Builder $query): Builder
    {
        return $query->where('es_presupuesto', true);
    }

    /** Solo los que encarecen el carro: la comisión del vendedor no cuenta. */
    public function scopeQueAfectanCosto(Builder $query): Builder
    {
        return $query->whereHas('categoria', fn (Builder $q) => $q->where('afecta_costo', true));
    }
}
