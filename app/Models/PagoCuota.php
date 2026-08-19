<?php

namespace App\Models;

use App\Models\Concerns\DejaRastro;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoCuota extends Model
{
    use DejaRastro, HasFactory, PerteneceAEmpresa;

    protected $table = 'pagos_cuota';

    protected $guarded = ['id'];

    /** Los defaults de la base, para que no aparezcan como cambios fantasma. */
    protected $attributes = [
        'mora' => 0,
    ];

    /** Lo que se sigue en el rastro: lo que mueve plata o cambia el negocio. */
    protected array $camposAuditados = ['monto', 'mora', 'anulado_en', 'motivo_anulacion'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
            'mora' => 'decimal:2',
            'anulado_en' => 'datetime',
        ];
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class);
    }

    public function movimientoCaja(): BelongsTo
    {
        return $this->belongsTo(MovimientoCaja::class, 'movimiento_caja_id');
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_en !== null;
    }

    /** Lo que efectivamente entró: abono más mora. */
    public function getTotalRecibidoAttribute(): string
    {
        return bcadd((string) $this->monto, (string) $this->mora, 2);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('anulado_en');
    }
}
