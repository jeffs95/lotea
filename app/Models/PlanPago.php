<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanPago extends Model
{
    use HasFactory, PerteneceAEmpresa;

    protected $table = 'planes_pago';

    protected $guarded = ['id'];

    public const ESTADOS = [
        'vigente' => 'Vigente',
        'cancelado' => 'Cancelado',
        'recuperado' => 'Unidad recuperada',
        'anulado' => 'Anulado',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'primera_cuota' => 'date',
            'precio_venta' => 'decimal:2',
            'enganche' => 'decimal:2',
            'monto_financiado' => 'decimal:2',
            'tasa_anual' => 'decimal:3',
            'tasa_mora_anual' => 'decimal:3',
            'cuota_mensual' => 'decimal:2',
            'gps_instalado' => 'boolean',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(Cuota::class)->orderBy('numero');
    }

    /**
     * Lo que falta por cobrar: capital e intereses de lo no pagado.
     *
     * Se calcula sobre la relación y no con sum() en la base, para que una
     * tabla que ya precargó las cuotas no vuelva a consultarlas por fila.
     */
    public function getSaldoAttribute(): string
    {
        $total = (string) $this->cuotas->sum('total');
        $pagado = (string) $this->cuotas->sum('pagado');

        return bcsub($total, $pagado, 2);
    }

    public function getSaldoCapitalAttribute(): string
    {
        $pendientes = $this->cuotas->where('estado', '!=', 'pagada');

        return (string) $pendientes->reduce(
            fn ($acc, Cuota $c) => bcadd($acc, bcsub((string) $c->capital, (string) $c->capitalCubierto(), 2), 2),
            '0.00',
        );
    }

    /** @return \Illuminate\Support\Collection<int, Cuota> */
    public function cuotasVencidas()
    {
        return $this->cuotas
            ->filter(fn (Cuota $cuota) => $cuota->estaVencida())
            ->values();
    }

    public function estaEnMora(): bool
    {
        return $this->cuotasVencidas()->isNotEmpty();
    }

    public function getDiasDeMoraAttribute(): int
    {
        $masVieja = $this->cuotasVencidas()->sortBy('vence_en')->first();

        return $masVieja ? (int) $masVieja->vence_en->diffInDays(now()) : 0;
    }

    public function getCuotasPagadasAttribute(): int
    {
        return $this->cuotas->where('estado', 'pagada')->count();
    }

    public function estaCancelado(): bool
    {
        return $this->estado === 'cancelado';
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estado', 'vigente');
    }
}
