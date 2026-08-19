<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sucursal extends Model
{
    use HasFactory, PerteneceAEmpresa, SoftDeletes;

    protected $table = 'sucursales';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
            'activa' => 'boolean',
        ];
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }
}
