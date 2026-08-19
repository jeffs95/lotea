<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdenTrabajo extends Model
{
    use HasFactory, PerteneceAEmpresa;

    protected $table = 'ordenes_trabajo';

    protected $guarded = ['id'];

    public const TIPOS = [
        'preparacion' => 'Preparación para venta',
        'reparacion' => 'Reparación',
        'garantia' => 'Garantía',
        'otro' => 'Otro',
    ];

    public const ESTADOS = [
        'abierta' => 'Abierta',
        'en_proceso' => 'En proceso',
        'terminada' => 'Terminada',
        'cerrada' => 'Cerrada',
        'anulada' => 'Anulada',
    ];

    protected function casts(): array
    {
        return [
            'abierta_en' => 'date',
            'terminada_en' => 'date',
            'cerrada_en' => 'datetime',
            'total_mano_obra' => 'decimal:2',
            'total_repuestos' => 'decimal:2',
            'total_terceros' => 'decimal:2',
            'costos_descargados' => 'boolean',
        ];
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function jefe(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'jefe_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(OtLinea::class, 'orden_trabajo_id');
    }

    public function getTotalAttribute(): string
    {
        return bcadd(
            bcadd((string) $this->total_mano_obra, (string) $this->total_repuestos, 2),
            (string) $this->total_terceros,
            2,
        );
    }

    public function estaCerrada(): bool
    {
        return in_array($this->estado, ['cerrada', 'anulada'], true);
    }

    public function estaAnulada(): bool
    {
        return $this->estado === 'anulada';
    }

    /** Se puede tocar mientras no se haya cerrado. */
    public function admiteCambios(): bool
    {
        return ! $this->estaCerrada();
    }

    public function getDiasEnTallerAttribute(): ?int
    {
        $hasta = $this->terminada_en ?? now();

        return $this->abierta_en ? (int) $this->abierta_en->diffInDays($hasta) : null;
    }

    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->whereIn('estado', ['abierta', 'en_proceso', 'terminada']);
    }
}
