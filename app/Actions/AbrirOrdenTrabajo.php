<?php

namespace App\Actions;

use App\Enums\EstadoUnidad;
use App\Models\OrdenTrabajo;
use App\Models\Unidad;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Abre una orden para una unidad y, si el carro ya está en el patio, lo manda
 * al taller.
 */
class AbrirOrdenTrabajo
{
    public function __construct(private CambiarEstadoUnidad $cambiarEstado) {}

    public function ejecutar(Unidad $unidad, array $datos = []): OrdenTrabajo
    {
        return DB::transaction(function () use ($unidad, $datos) {
            $orden = OrdenTrabajo::create([
                'unidad_id' => $unidad->id,
                'sucursal_id' => $datos['sucursal_id'] ?? $unidad->sucursal_id,
                'jefe_id' => $datos['jefe_id'] ?? null,
                'numero' => $datos['numero'] ?? $this->siguienteNumero(),
                'tipo' => $datos['tipo'] ?? 'preparacion',
                'estado' => $datos['estado'] ?? 'abierta',
                'abierta_en' => $datos['abierta_en'] ?? now()->toDateString(),
                'diagnostico' => $datos['diagnostico'] ?? null,
                'notas' => $datos['notas'] ?? null,
                'user_id' => Auth::id(),
            ]);

            // Solo si la máquina de estados lo permite: una unidad que todavía
            // viene en el barco no puede entrar al taller.
            if ($unidad->estado->puedePasarA(EstadoUnidad::EnTaller)) {
                $this->cambiarEstado->ejecutar($unidad, EstadoUnidad::EnTaller, "Orden {$orden->numero}");
            }

            return $orden;
        });
    }

    protected function siguienteNumero(): string
    {
        $ultimo = OrdenTrabajo::where('empresa_id', Tenancy::empresaId())->count();

        return 'OT-'.str_pad((string) ($ultimo + 1), 4, '0', STR_PAD_LEFT);
    }
}
