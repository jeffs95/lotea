<?php

namespace App\Models;

use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        ];
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
        return $this->hasMany(Sucursal::class);
    }

    public function categoriasCosto(): HasMany
    {
        return $this->hasMany(CategoriaCosto::class);
    }

    public function proveedores(): HasMany
    {
        return $this->hasMany(Proveedor::class);
    }
}
