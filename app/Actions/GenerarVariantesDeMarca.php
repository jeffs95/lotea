<?php

namespace App\Actions;

use App\Models\Empresa;
use App\Support\AlmacenDeArchivos;
use App\Support\Tenancy;
use App\Support\VariantesDeLogo;
use Throwable;

/**
 * Deriva del logo original las versiones que el sistema necesita.
 *
 * Corre desde dos lados: el comando, para procesar clientes en lote, y la
 * pantalla de marca, en cuanto el concesionario sube su logo. Así la promesa de
 * «lo que deje vacío se llena solo» es verdad y no un texto de ayuda.
 */
class GenerarVariantesDeMarca
{
    /**
     * @param  bool  $forzar  rehace también lo que el cliente ya eligió
     * @return array<string, string> campo => ruta, de lo que quedó asignado
     */
    public function ejecutar(Empresa $empresa, bool $forzar = false): array
    {
        $origen = Tenancy::comoEmpresa($empresa, fn () => $empresa->archivoDeMarcaLocal('logo_path'));

        if (! $origen) {
            return [];
        }

        try {
            $variantes = VariantesDeLogo::desde($origen, $empresa->color_de_marca);
        } catch (Throwable) {
            // Un archivo que GD no entiende no puede tumbar el guardado de la
            // ficha: el cliente se queda con su logo original y nada más.
            return [];
        }

        $rutas = $this->guardar($empresa, $variantes);

        return $this->asignar($empresa, $rutas, $forzar);
    }

    /**
     * @param  array<string, \GdImage>  $variantes
     * @return array<string, string>
     */
    protected function guardar(Empresa $empresa, array $variantes): array
    {
        $disco = AlmacenDeArchivos::disco();
        $rutas = [];

        foreach ($variantes as $nombre => $imagen) {
            $ruta = "marcas/{$empresa->slug}/variantes/{$nombre}.png";

            $disco->put($ruta, VariantesDeLogo::aPng($imagen));
            $rutas[$nombre] = $ruta;

            imagedestroy($imagen);
        }

        return $rutas;
    }

    /**
     * Qué variante va a qué campo.
     *
     * Lo que el cliente subió a mano manda: solo se llenan los huecos, salvo
     * que se pida rehacer todo.
     *
     * @param  array<string, string>  $rutas
     * @return array<string, string>
     */
    protected function asignar(Empresa $empresa, array $rutas, bool $forzar): array
    {
        $asignaciones = [
            // Barra y pie del portal, etiquetas, panel de día.
            'logo_claro_path' => $rutas['isologo-claro'] ?? null,

            // Portada del portal y panel de noche.
            'logo_oscuro_path' => $rutas['isologo'] ?? null,

            // Centro del QR: ahí el nombre no se lee y va sobre blanco.
            'isotipo_path' => $rutas['isotipo-claro'] ?? null,

            // Pestaña en navegadores que no entienden iconos SVG.
            'favicon_path' => $rutas['favicon'] ?? null,
        ];

        $cambios = [];

        foreach ($asignaciones as $campo => $ruta) {
            if ($ruta && ($forzar || blank($empresa->{$campo}))) {
                $cambios[$campo] = $ruta;
            }
        }

        if ($cambios !== []) {
            $empresa->update($cambios);
        }

        return $cambios;
    }
}
