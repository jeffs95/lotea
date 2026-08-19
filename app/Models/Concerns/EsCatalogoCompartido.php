<?php

namespace App\Models\Concerns;

use App\Models\Empresa;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Para catálogos que Lotea mantiene para todos (marcas, líneas) pero que cada
 * cliente puede ampliar con los suyos.
 *
 * empresa_id null = fila del sistema, visible para todos.
 * empresa_id X    = fila que creó ese cliente, visible solo para él.
 */
trait EsCatalogoCompartido
{
    public static function bootEsCatalogoCompartido(): void
    {
        static::addGlobalScope('catalogoCompartido', function (Builder $builder) {
            if (! Tenancy::filtrando()) {
                return;
            }

            $columna = $builder->getModel()->qualifyColumn('empresa_id');

            $builder->where(
                fn (Builder $q) => $q->whereNull($columna)->orWhere($columna, Tenancy::empresaId())
            );
        });

        static::creating(function (Model $modelo) {
            if ($modelo->empresa_id === null && Tenancy::hayEmpresa()) {
                $modelo->empresa_id = Tenancy::empresaId();
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function esDelSistema(): bool
    {
        return $this->empresa_id === null;
    }
}
