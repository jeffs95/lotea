<?php

namespace App\Models;

use App\Models\Concerns\EsCatalogoCompartido;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marca extends Model
{
    use EsCatalogoCompartido, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(Linea::class);
    }
}
