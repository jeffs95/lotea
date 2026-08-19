<?php

namespace App\Models;

use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * El tenant. Cada empresa es un concesionario cliente de Lotea y no ve
 * absolutamente nada de las demás.
 *
 * Este modelo es de los poquísimos que NO usa PerteneceAEmpresa: es la raíz.
 */
class Empresa extends Model implements HasName
{
    use HasFactory, SoftDeletes;

    protected $table = 'empresas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'fecha_activacion' => 'date',
            'fecha_vencimiento' => 'date',
            'suspendida_en' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function cobros(): HasMany
    {
        return $this->hasMany(Cobro::class);
    }

    public function unidades(): HasMany
    {
        return $this->sinScopeDeEmpresa($this->hasMany(Unidad::class));
    }

    public function lecturasIa(): HasMany
    {
        return $this->sinScopeDeEmpresa($this->hasMany(LecturaIa::class));
    }

    /**
     * Quita el filtro por empresa activa de una relación de esta empresa.
     *
     * La relación ya acota por empresa_id, así que el scope encima no agrega
     * seguridad: solo hace que `$otraEmpresa->unidades` devuelva vacío cuando
     * el contexto activo es distinto, que es exactamente lo que necesita el
     * panel central para ver a todos los clientes.
     */
    protected function sinScopeDeEmpresa(HasMany $relacion): HasMany
    {
        return $relacion->withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class);
    }

    /** ¿Este cliente contrató el módulo? */
    public function tieneModulo(string $modulo): bool
    {
        return (bool) $this->plan?->permite($modulo);
    }

    public function lecturasIaDelMes(?string $periodo = null): int
    {
        return $this->lecturasIa()->exitosas()->delMes($periodo)->count();
    }

    public function costoIaDelMes(?string $periodo = null): float
    {
        return (float) $this->lecturasIa()->delMes($periodo)->sum('costo_usd');
    }

    /** Le queda cupo este mes, o el plan no tiene tope. */
    public function puedeLeerConIa(): bool
    {
        if (! $this->tieneModulo('ia')) {
            return false;
        }

        $tope = $this->plan?->max_lecturas_ia;

        return $tope === null || $this->lecturasIaDelMes() < $tope;
    }

    public function estaSuspendida(): bool
    {
        return $this->suspendida_en !== null;
    }

    /** Puede operar: ni dada de baja ni suspendida por falta de pago. */
    public function puedeOperar(): bool
    {
        return $this->activa && ! $this->estaSuspendida();
    }

    public function getEstadoSuscripcionAttribute(): string
    {
        return match (true) {
            ! $this->activa => 'baja',
            $this->estaSuspendida() => 'suspendida',
            default => 'activa',
        };
    }

    /** Lo que factura al mes. Es la unidad del MRR. */
    public function getMensualidadAttribute(): float
    {
        return $this->puedeOperar() ? (float) ($this->plan?->precio_mensual ?? 0) : 0.0;
    }

    /** Lo que se ve en el selector de empresa del panel. */
    public function getFilamentName(): string
    {
        return $this->nombre_comercial ?: $this->nombre;
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function sucursales(): HasMany
    {
        return $this->sinScopeDeEmpresa($this->hasMany(Sucursal::class));
    }

    public function categoriasCosto(): HasMany
    {
        return $this->sinScopeDeEmpresa($this->hasMany(CategoriaCosto::class));
    }

    public function proveedores(): HasMany
    {
        return $this->sinScopeDeEmpresa($this->hasMany(Proveedor::class));
    }
}
