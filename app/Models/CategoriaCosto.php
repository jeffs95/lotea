<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoriaCosto extends Model
{
    use HasFactory, PerteneceAEmpresa;

    protected $table = 'categorias_costo';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'afecta_costo' => 'boolean',
            'prorrateable' => 'boolean',
            'es_sistema' => 'boolean',
            'activa' => 'boolean',
        ];
    }
}
