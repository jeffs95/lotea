<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Caja extends Model
{
    use HasFactory, PerteneceAEmpresa, SoftDeletes;

    protected $table = 'cajas';

    protected $guarded = ['id'];

    public const TIPOS = ['efectivo' => 'Efectivo', 'banco' => 'Cuenta bancaria'];

    protected function casts(): array
    {
        return [
            'saldo_inicial' => 'decimal:2',
            'activa' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCaja::class);
    }

    public function arqueos(): HasMany
    {
        return $this->hasMany(Arqueo::class)->latest('realizado_en');
    }

    /**
     * El saldo se calcula, no se guarda.
     *
     * Un saldo almacenado se desincroniza el día que alguien anula un
     * movimiento viejo, y nadie se entera hasta el arqueo.
     */
    public function getSaldoAttribute(): string
    {
        $vigentes = $this->movimientos()->vigentes();

        $ingresos = (string) ((clone $vigentes)->where('tipo', 'ingreso')->sum('monto'));
        $egresos = (string) ((clone $vigentes)->where('tipo', 'egreso')->sum('monto'));

        return bcadd(
            (string) $this->saldo_inicial,
            bcsub($ingresos, $egresos, 2),
            2,
        );
    }

    /** El mismo saldo pero en quetzales, para poder sumar cajas de distinta moneda. */
    public function getSaldoEnQuetzalesAttribute(): string
    {
        $vigentes = $this->movimientos()->vigentes();

        $ingresos = (string) ((clone $vigentes)->where('tipo', 'ingreso')->sum('monto_base'));
        $egresos = (string) ((clone $vigentes)->where('tipo', 'egreso')->sum('monto_base'));

        $inicial = $this->moneda === 'GTQ' ? (string) $this->saldo_inicial : '0.00';

        return bcadd($inicial, bcsub($ingresos, $egresos, 2), 2);
    }

    public function esEnDolares(): bool
    {
        return $this->moneda === 'USD';
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }
}
