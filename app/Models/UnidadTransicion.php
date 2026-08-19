<?php

namespace App\Models;

use App\Enums\EstadoUnidad;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Historial inmutable de estados. Se inserta y no se toca más. */
class UnidadTransicion extends Model
{
    use HasFactory, PerteneceAEmpresa;

    protected $table = 'unidad_transiciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'estado_anterior' => EstadoUnidad::class,
            'estado_nuevo' => EstadoUnidad::class,
            'ocurrio_en' => 'datetime',
        ];
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
