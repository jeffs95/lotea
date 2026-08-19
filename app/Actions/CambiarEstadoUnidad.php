<?php

namespace App\Actions;

use App\Enums\EstadoUnidad;
use App\Models\Unidad;
use App\Models\UnidadTransicion;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Único camino para mover una unidad de estado.
 *
 * Nadie debería hacer $unidad->update(['estado' => ...]) por su cuenta: aquí
 * es donde se valida la transición, se deja el rastro y se marcan las fechas
 * hito de las que después salen los días de rotación.
 */
class CambiarEstadoUnidad
{
    public function ejecutar(Unidad $unidad, EstadoUnidad $destino, ?string $nota = null, ?int $userId = null): Unidad
    {
        $origen = $unidad->estado;

        if ($origen === $destino) {
            return $unidad;
        }

        if (! $origen->puedePasarA($destino)) {
            throw new DomainException(
                "Una unidad en «{$origen->getLabel()}» no puede pasar a «{$destino->getLabel()}»."
            );
        }

        return DB::transaction(function () use ($unidad, $origen, $destino, $nota, $userId) {
            $ahora = now();

            UnidadTransicion::create([
                'unidad_id' => $unidad->id,
                'user_id' => $userId ?? Auth::id(),
                'estado_anterior' => $origen,
                'estado_nuevo' => $destino,
                'ocurrio_en' => $ahora,
                'dias_en_estado_anterior' => $unidad->estado_desde
                    ? (int) $unidad->estado_desde->diffInDays($ahora)
                    : null,
                'nota' => $nota,
            ]);

            $cambios = [
                'estado' => $destino,
                'estado_desde' => $ahora,
            ];

            // Fechas hito: se sellan la primera vez que se llega, no cada vez
            // que se rebobina (un carro puede volver al taller y salir otra vez).
            $cambios += match ($destino) {
                EstadoUnidad::Recibida => $unidad->fecha_recepcion ? [] : ['fecha_recepcion' => $ahora->toDateString()],
                EstadoUnidad::Lista => $unidad->fecha_lista ? [] : ['fecha_lista' => $ahora->toDateString()],
                EstadoUnidad::Vendida => $unidad->fecha_venta ? [] : ['fecha_venta' => $ahora->toDateString()],
                default => [],
            };

            // Al salir del escaparate, deja de estar publicada.
            if (in_array($destino, [EstadoUnidad::Vendida, EstadoUnidad::Entregada, EstadoUnidad::Baja], true)) {
                $cambios['publicado'] = false;
            }

            $unidad->update($cambios);

            return $unidad->refresh();
        });
    }
}
