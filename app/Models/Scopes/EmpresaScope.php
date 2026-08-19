<?php

namespace App\Models\Scopes;

use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limita toda consulta a la empresa activa.
 *
 * Se aplica solo desde el trait PerteneceAEmpresa, nunca a mano.
 */
class EmpresaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! Tenancy::filtrando()) {
            return;
        }

        $builder->where($model->qualifyColumn('empresa_id'), Tenancy::empresaId());
    }
}
