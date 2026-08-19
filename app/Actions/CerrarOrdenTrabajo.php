<?php

namespace App\Actions;

use App\Models\CategoriaCosto;
use App\Models\OrdenTrabajo;
use App\Models\OtLinea;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Cierra la orden y descarga su costo en la unidad.
 *
 * Es el momento en que el trabajo del taller se vuelve parte del costo del
 * carro. Se descarga un renglón por tipo (mano de obra, repuestos, terceros)
 * y no línea por línea: la ficha de rentabilidad se lee mejor así, y el
 * detalle sigue estando en la orden.
 */
class CerrarOrdenTrabajo
{
    public function __construct(
        private RegistrarCosto $registrarCosto,
        private RecalcularOrdenTrabajo $recalcular,
    ) {}

    public function ejecutar(OrdenTrabajo $orden): OrdenTrabajo
    {
        if ($orden->estaCerrada()) {
            throw new DomainException("La orden {$orden->numero} ya estaba cerrada.");
        }

        if ($orden->lineas()->doesntExist()) {
            throw new DomainException('No se puede cerrar una orden sin trabajo registrado.');
        }

        return DB::transaction(function () use ($orden) {
            $this->recalcular->ejecutar($orden);

            if (! $orden->costos_descargados) {
                $this->descargarCostos($orden);
            }

            $orden->update([
                'estado' => 'cerrada',
                'cerrada_en' => now(),
                'terminada_en' => $orden->terminada_en ?? now()->toDateString(),
                'costos_descargados' => true,
            ]);

            return $orden->refresh();
        });
    }

    protected function descargarCostos(OrdenTrabajo $orden): void
    {
        $totales = [
            'mano_obra' => $orden->total_mano_obra,
            'repuesto' => $orden->total_repuestos,
            'tercero' => $orden->total_terceros,
        ];

        foreach ($totales as $tipo => $monto) {
            if (bccomp((string) $monto, '0.00', 2) <= 0) {
                continue;
            }

            $categoria = CategoriaCosto::where('codigo', OtLinea::CATEGORIAS[$tipo])->first();

            if (! $categoria) {
                continue;
            }

            $this->registrarCosto->ejecutar($orden->unidad, [
                'categoria_costo_id' => $categoria->id,
                'monto' => $monto,
                'fecha' => $orden->terminada_en ?? now()->toDateString(),
                'descripcion' => OtLinea::TIPOS[$tipo]." · orden {$orden->numero}",
                'documento' => $orden->numero,
            ]);
        }
    }
}
