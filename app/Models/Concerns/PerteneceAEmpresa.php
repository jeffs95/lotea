<?php

namespace App\Models\Concerns;

use App\Models\Empresa;
use App\Models\Scopes\EmpresaScope;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Todo modelo de negocio usa este trait. Sin excepciones.
 *
 * Hace tres cosas: filtra las consultas por la empresa activa, rellena
 * empresa_id solo al crear, y prohíbe mover un registro de una empresa a otra.
 */
trait PerteneceAEmpresa
{
    public static function bootPerteneceAEmpresa(): void
    {
        static::addGlobalScope(new EmpresaScope);

        static::creating(function (Model $modelo) {
            if ($modelo->empresa_id === null && Tenancy::hayEmpresa()) {
                $modelo->empresa_id = Tenancy::empresaId();
            }

            if ($modelo->empresa_id === null) {
                throw new RuntimeException(
                    'Se intentó crear un '.class_basename($modelo).' sin empresa. '
                    .'Fijá el contexto con Tenancy::usar() o asigná empresa_id explícitamente.'
                );
            }
        });

        static::updating(function (Model $modelo) {
            if ($modelo->isDirty('empresa_id')) {
                throw new RuntimeException(
                    'No se puede mover un '.class_basename($modelo).' de una empresa a otra.'
                );
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
