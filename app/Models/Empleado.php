<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empleado extends Model
{
    use HasFactory, PerteneceAEmpresa, SoftDeletes;

    protected $guarded = ['id'];

    public const AREAS = [
        'ventas' => 'Ventas',
        'taller' => 'Taller',
        'administracion' => 'Administración',
        'gerencia' => 'Gerencia',
    ];

    public const CONTRATOS = [
        'indefinido' => 'Indefinido',
        'plazo_fijo' => 'Plazo fijo',
        'temporal' => 'Temporal',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'fecha_ingreso' => 'date',
            'fecha_baja' => 'date',
            'salario_base' => 'decimal:2',
            'bonificacion_incentivo' => 'decimal:2',
            'costo_hora' => 'decimal:2',
            'es_mecanico' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /** La cuenta con la que entra al sistema, si tiene. */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombres} {$this->apellidos}");
    }

    /** Lo que cobra al mes antes de deducciones. */
    public function getIngresoMensualAttribute(): string
    {
        return bcadd((string) $this->salario_base, (string) $this->bonificacion_incentivo, 2);
    }

    /** Años cumplidos en la empresa: lo que manda para prestaciones. */
    public function getAntiguedadAttribute(): ?float
    {
        $hasta = $this->fecha_baja ?? now();

        return $this->fecha_ingreso ? round($this->fecha_ingreso->diffInDays($hasta) / 365, 1) : null;
    }

    public function estaDeBaja(): bool
    {
        return $this->fecha_baja !== null;
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true)->whereNull('fecha_baja');
    }

    public function scopeMecanicos(Builder $query): Builder
    {
        return $query->activos()->where('es_mecanico', true);
    }
}
