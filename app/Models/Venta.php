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
            'comision_acordada' => 'decimal:2',
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

    /**
     * Quién escribió la venta en el sistema, que no siempre es quien la hizo.
     *
     * Los vendedores externos cargan lo suyo cuando pueden, y más de uno lo
     * hace el viernes de corrido. La venta queda firme al cargarla —así se
     * decidió, sin paso de aprobación—, de modo que esto es lo que queda para
     * revisar después: quién la escribió y cuánto después del día de la venta.
     */
    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Días entre el día de la venta y el día en que alguien la escribió. */
    public function diasEntreVentaYCarga(): int
    {
        if ($this->fecha === null || $this->created_at === null) {
            return 0;
        }

        return max(0, (int) $this->fecha->startOfDay()
            ->diffInDays($this->created_at->startOfDay(), false));
    }

    /** Cuándo se cargó, y si fue bastante después, cuánto. */
    public function getResumenDeCargaAttribute(): ?string
    {
        if ($this->created_at === null) {
            return null;
        }

        $cuando = $this->created_at->format('d/m/Y');
        $dias = $this->diasEntreVentaYCarga();

        if ($dias === 0) {
            return $cuando;
        }

        return $cuando.' · '.$dias.($dias === 1 ? ' día' : ' días').' después';
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

    /**
     * Suma de los cobros vigentes, en quetzales.
     *
     * Si la consulta ya lo trajo con withSum (como hace el listado), se usa
     * ese valor: preguntarlo de nuevo por cada fila es el N+1 clásico.
     */
    public function getCobradoAttribute(): string
    {
        if (array_key_exists('cobrado', $this->attributes)) {
            return (string) ($this->attributes['cobrado'] ?? 0);
        }

        return (string) $this->movimientosCaja()->vigentes()->sum('monto_base');
    }

    /** Lo que el cliente todavía debe de este carro. */
    public function getSaldoPendienteAttribute(): string
    {
        return bcsub((string) $this->precio_final, $this->cobrado, 2);
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

    /**
     * Lo que este usuario tiene derecho a ver.
     *
     * Varios vendedores no son empleados del patio: publican los carros por su
     * cuenta y cobran por unidad vendida, con un trato distinto cada uno. Que
     * uno abra la pantalla de ventas y lea lo que cobró el otro es un problema
     * entre personas, y de los que no se arreglan después.
     *
     * Suya es la venta en la que él figura como vendedor, no la que él cargó:
     * si el dueño corrige después a quién le toca la comisión, la venta sigue
     * a la comisión y no a quien la escribió.
     *
     * Sin usuario no se devuelve nada. Una consulta sin sesión es un error de
     * programación, y entre fallar y enseñarlo todo, falla.
     */
    public function scopeVisiblesPara(Builder $query, ?User $usuario): Builder
    {
        if ($usuario === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($usuario->can('ver_ventas_ajenas')) {
            return $query;
        }

        return $query->where('vendedor_id', $usuario->getKey());
    }
}
