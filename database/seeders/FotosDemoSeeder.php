<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Unidad;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

/**
 * Fotos de relleno para las unidades de demostración.
 *
 * No es adorno: una unidad publicada sin foto no cumple los requisitos para
 * estar en el portal, así que el modelo la despublica en cuanto alguien la
 * edita y la demo se va vaciando sola. Además, un portal de demostración sin
 * fotos no vende nada.
 */
class FotosDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Tenancy::hayEmpresa()) {
            Tenancy::usar(Empresa::firstWhere('slug', 'autos-del-valle'));
        }

        Unidad::query()
            ->where('publicado', true)
            ->with(['marca', 'linea', 'media'])
            ->cursor()
            ->each(function (Unidad $unidad) {
                if ($unidad->getMedia('fotos')->isNotEmpty()) {
                    return;
                }

                foreach (['Frente', 'Lateral', 'Interior'] as $vista) {
                    $unidad->addMediaFromString($this->imagen($unidad, $vista))
                        ->usingFileName(str($unidad->stock_no.'-'.$vista)->slug().'.jpg')
                        ->toMediaCollection('fotos');
                }
            });
    }

    /** Un rectángulo con el modelo escrito: suficiente para ver el portal armado. */
    protected function imagen(Unidad $unidad, string $vista): string
    {
        $ancho = 1200;
        $alto = 900;

        $lienzo = imagecreatetruecolor($ancho, $alto);

        // Un tono distinto por unidad para que el catálogo no se vea repetido.
        $matiz = ($unidad->id * 37) % 60;
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 40 + $matiz, 44 + $matiz, 52 + $matiz));

        $texto = imagecolorallocate($lienzo, 235, 235, 240);
        $tenue = imagecolorallocate($lienzo, 150, 150, 160);

        imagestring($lienzo, 5, 60, $alto / 2 - 40, $unidad->descripcion, $texto);
        imagestring($lienzo, 3, 60, $alto / 2, 'Stock '.$unidad->stock_no.' · '.$vista, $tenue);
        imagestring($lienzo, 2, 60, $alto - 60, 'Foto de demostración', $tenue);

        ob_start();
        imagejpeg($lienzo, null, 85);
        $binario = ob_get_clean();
        imagedestroy($lienzo);

        return $binario;
    }
}
