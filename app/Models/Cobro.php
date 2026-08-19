<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una mensualidad. Tampoco lleva empresa_id como scope: es del proveedor y se
 * consulta a través del panel central, que ve todas las empresas.
 */
class Cobro extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'pagado' => 'Pagado',
        'vencido' => 'Vencido',
        'condonado' => 'Condonado',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'vence_en' => 'date',
            'pagado_en' => 'date',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function estaPagado(): bool
    {
        return in_array($this->estado, ['pagado', 'condonado'], true);
    }

    /**
     * Vencido de verdad: no pagado y con la fecha ya pasada.
     *
     * El día del vencimiento todavía no cuenta: el cliente tiene ese día
     * completo para pagar.
     */
    public function estaVencido(): bool
    {
        return ! $this->estaPagado() && $this->vence_en->isBefore(today());
    }

    public function getDiasDeMoraAttribute(): int
    {
        return $this->estaVencido() ? (int) $this->vence_en->diffInDays(now()) : 0;
    }

    public function scopePorCobrar(Builder $query): Builder
    {
        return $query->whereNotIn('estado', ['pagado', 'condonado']);
    }
}
