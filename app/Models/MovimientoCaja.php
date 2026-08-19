<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MovimientoCaja extends Model
{
    use HasFactory, PerteneceAEmpresa;

    protected $table = 'movimientos_caja';

    protected $guarded = ['id'];

    public const TIPOS = ['ingreso' => 'Ingreso', 'egreso' => 'Egreso'];

    public const CATEGORIAS = [
        'venta' => 'Venta de unidad',
        'enganche' => 'Enganche',
        'cuota' => 'Cuota de crédito',
        'gasto' => 'Gasto',
        'traslado' => 'Traslado entre cajas',
        'aporte' => 'Aporte del dueño',
        'retiro' => 'Retiro del dueño',
        'otro' => 'Otro',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
            'tipo_cambio' => 'decimal:6',
            'monto_base' => 'decimal:2',
            'anulado_en' => 'datetime',
        ];
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function origen(): MorphTo
    {
        return $this->morphTo();
    }

    public function contraparte(): BelongsTo
    {
        return $this->belongsTo(self::class, 'contraparte_id');
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_en !== null;
    }

    public function esIngreso(): bool
    {
        return $this->tipo === 'ingreso';
    }

    /** Con signo, para leer un estado de cuenta de corrido. */
    public function getMontoConSignoAttribute(): string
    {
        return $this->esIngreso() ? (string) $this->monto : bcmul((string) $this->monto, '-1', 2);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('anulado_en');
    }
}
