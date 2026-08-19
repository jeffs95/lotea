<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Global a todos los tenants: el Banguat publica uno solo. */
class TipoCambio extends Model
{
    use HasFactory;

    protected $table = 'tipos_cambio';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'tasa' => 'decimal:6',
        ];
    }
}
