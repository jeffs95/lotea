<?php

namespace App\Models;

use App\Models\Concerns\DejaRastro;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Venta extends Model
{
    use DejaRastro, HasFactory, PerteneceAEmpresa;

    protected $guarded = ['id'];

    /** Los defaults de la base, para que no aparezcan como cambios fantasma. */
    protected $attributes = [
        'estado' => 'cotizacion',
        'descuento' => 0,
        'forma_pago' => 'contado',
        'comision_base' => 'margen',
        'comision_porcentaje' => 0,
        'comision_monto' => 0,
        'comision_pagada' => false,
    ];

    /** Lo que se sigue en el rastro: lo que mueve plata o cambia el negocio. */
    protected array $camposAuditados = ['estado', 'precio_venta', 'descuento', 'precio_final', 'comision_monto', 'forma_pago', 'anulada_en', 'motivo_anulacion'];

    public const ESTADOS = [
        'cotizacion' => 'Cotización',
        'reservada' => 'Reservada',
        'cerrada' => 'Cerrada',
        'anulada' => 'Anulada',
    ];

    public const FORMAS_PAGO = [
        'contado' => 'Contado',
        'financiamiento_banco' => 'Financiamiento bancario',
        'credito_propio' => 'Crédito propio',
        'mixto' => 'Mixto',
    ];

    public const BASES_COMISION = [
        'margen' => 'Sobre la utilidad',
        'precio' => 'Sobre el precio de venta',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'deposito' => 'decimal:2',
            'deposito_vence_en' => 'date',
            'precio_venta' => 'decimal:2',
            'descuento' => 'decimal:2',
            'precio_final' => 'decimal:2',
            'enganche' => 'decimal:2',
            'saldo_financiado' => 'decimal:2',
            'comision_porcentaje' => 'decimal:3',
            'comision_monto' => 'decimal:2',
            'comision_pagada' => 'boolean',
            'factura_fecha' => 'date',
            'entregada_en' => 'date',
            'anulada_en' => 'datetime',
        ];
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function planPago(): HasOne
    {
        return $this->hasOne(PlanPago::class);
    }

    /**
     * Lo que el cliente ya entregó por este carro: enganche, abonos, el pago
     * completo. Es la contraparte del enlace que deja CobrarVentaEnCaja.
     */
    public function movimientosCaja(): MorphMany
    {
        return $this->morphMany(MovimientoCaja::class, 'origen');
    }

    /** Suma de los cobros vigentes, en quetzales. */
    public function getCobradoAttribute(): string
    {
        return (string) $this->movimientosCaja()->vigentes()->sum('monto_base');
    }

    public function esACreditoPropio(): bool
    {
        return $this->forma_pago === 'credito_propio';
    }

    public function estaCerrada(): bool
    {
        return $this->estado === 'cerrada';
    }

    public function estaAnulada(): bool
    {
        return $this->anulada_en !== null;
    }

    /** Lo que queda después del costo de la unidad y de la comisión. */
    public function getUtilidadAttribute(): string
    {
        return bcsub(
            bcsub((string) $this->precio_final, (string) $this->unidad->costo_total, 2),
            (string) $this->comision_monto,
            2,
        );
    }

    public function getMargenAttribute(): ?float
    {
        return (float) $this->precio_final > 0
            ? ((float) $this->utilidad / (float) $this->precio_final) * 100
            : null;
    }

    /** Cuánto se movió el precio real respecto de lo que se pedía. */
    public function getDiferenciaContraListaAttribute(): ?string
    {
        return $this->unidad->precio_lista !== null
            ? bcsub((string) $this->precio_final, (string) $this->unidad->precio_lista, 2)
            : null;
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('anulada_en');
    }

    public function scopeCerradas(Builder $query): Builder
    {
        return $query->vigentes()->where('estado', 'cerrada');
    }
}
