<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un plan de Lotea. No lleva empresa_id: es del proveedor, no de los clientes.
 */
class Plan extends Model
{
    use HasFactory;

    protected $table = 'planes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'precio_mensual' => 'decimal:2',
            'modulos' => 'array',
            'activo' => 'boolean',
        ];
    }

    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class);
    }

    public function permite(string $modulo): bool
    {
        return in_array($modulo, $this->modulos ?? [], true);
    }

    public function limiteTexto(?int $limite, string $singular, string $plural): string
    {
        return $limite === null ? "{$plural} ilimitadas" : "{$limite} ".($limite === 1 ? $singular : $plural);
    }
}
