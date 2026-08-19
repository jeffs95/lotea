<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GastoCompartido extends Model
{
    use HasFactory, PerteneceAEmpresa;

    protected $table = 'gastos_compartidos';

    protected $guarded = ['id'];

    public const CRITERIOS = [
        'partes_iguales' => 'Partes iguales',
        'por_valor' => 'Proporcional al costo de cada unidad',
    ];

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

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaCosto::class, 'categoria_costo_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /** Las porciones que este gasto generó en cada unidad. */
    public function porciones(): HasMany
    {
        return $this->hasMany(CostoUnidad::class, 'prorrateado_de_id');
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_en !== null;
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('anulado_en');
    }
}
