<?php

namespace App\Actions;

use App\Models\Empresa;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Corta el acceso de un cliente sin borrarle nada.
 *
 * Suspender no es dar de baja: los datos siguen ahí y el día que paguen se
 * levanta la suspensión y siguen trabajando donde quedaron. Un cliente que
 * pierde su historial no vuelve.
 */
class SuspenderConcesionario
{
    public function suspender(Empresa $empresa, string $motivo): Empresa
    {
        if (blank(trim($motivo))) {
            throw new DomainException('Hay que decir por qué se suspende.');
        }

        return DB::transaction(function () use ($empresa, $motivo) {
            $empresa->update([
                'suspendida_en' => now(),
                'motivo_suspension' => trim($motivo),
            ]);

            return $empresa->refresh();
        });
    }

    public function reactivar(Empresa $empresa): Empresa
    {
        $empresa->update([
            'suspendida_en' => null,
            'motivo_suspension' => null,
        ]);

        return $empresa->refresh();
    }
}
