<?php

namespace App\Models;

use App\Models\Concerns\EsCatalogoCompartido;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Linea extends Model
{
    use EsCatalogoCompartido, HasFactory;

    protected $table = 'lineas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(Unidad::class);
    }
}
