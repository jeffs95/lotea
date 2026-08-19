<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cuota extends Model
{
    use HasFactory, PerteneceAEmpresa;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'vence_en' => 'date',
            'pagada_en' => 'date',
            'capital' => 'decimal:2',
            'interes' => 'decimal:2',
            'total' => 'decimal:2',
            'saldo_despues' => 'decimal:2',
            'pagado' => 'decimal:2',
            'mora_cobrada' => 'decimal:2',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanPago::class, 'plan_pago_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoCuota::class);
    }

    public function estaPagada(): bool
    {
        return $this->estado === 'pagada';
    }

    public function getPendienteAttribute(): string
    {
        return bcsub((string) $this->total, (string) $this->pagado, 2);
    }

    /** Cuánto del capital ya quedó cubierto por lo abonado. */
    public function capitalCubierto(): string
    {
        if ($this->estaPagada()) {
            return (string) $this->capital;
        }

        // Lo abonado se aplica primero al interés y después al capital.
        $sobrante = bcsub((string) $this->pagado, (string) $this->interes, 2);

        return bccomp($sobrante, '0.00', 2) > 0 ? $sobrante : '0.00';
    }

    /**
     * Vencida es la que se pasó de fecha, no la que vence hoy.
     *
     * El cliente tiene todo el día del vencimiento para pagar: marcarla en
     * mora desde la medianoche es cobrarle mora por un día que no debe.
     */
    public function estaVencida(): bool
    {
        return ! $this->estaPagada() && $this->vence_en->isBefore(today());
    }

    public function getDiasDeAtrasoAttribute(): int
    {
        return $this->estaVencida() ? (int) $this->vence_en->diffInDays(now()) : 0;
    }

    /**
     * La mora se calcula al momento, no se guarda: cambia cada día que pasa.
     */
    public function moraAlDia(?float $tasaAnual = null): string
    {
        $tasa = (string) ($tasaAnual ?? $this->plan->tasa_mora_anual);

        if (bccomp($tasa, '0', 3) === 0 || ! $this->estaVencida()) {
            return '0.00';
        }

        $diaria = bcdiv(bcdiv($tasa, '100', 8), '365', 10);

        return bcmul(bcmul($this->pendiente, $diaria, 8), (string) $this->dias_de_atraso, 2);
    }

    public function scopeVencidas(Builder $query): Builder
    {
        return $query->where('estado', '!=', 'pagada')->whereDate('vence_en', '<', today());
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', '!=', 'pagada');
    }
}
