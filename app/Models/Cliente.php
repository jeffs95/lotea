<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use HasFactory, PerteneceAEmpresa, SoftDeletes;

    protected $guarded = ['id'];

    public const TIPOS = ['persona' => 'Persona individual', 'empresa' => 'Empresa'];

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function getEtiquetaAttribute(): string
    {
        return $this->nit ? "{$this->nombre} · NIT {$this->nit}" : $this->nombre;
    }
}
