<?php

namespace App\Support;

use App\Models\Unidad;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * El QR que se pega en el parabrisas.
 *
 * En SVG y no en PNG: se imprime nítido a cualquier tamaño y no necesita
 * imagick en el servidor.
 */
class QrDeUnidad
{
    public static function url(Unidad $unidad): string
    {
        return route('escaneo', ['codigo' => $unidad->codigo_qr]);
    }

    public static function svg(Unidad $unidad, int $tamano = 200): string
    {
        return (string) QrCode::format('svg')
            ->size($tamano)
            ->margin(0)
            ->errorCorrection('M')   // tolera que la etiqueta se ensucie o se raye
            ->generate(self::url($unidad));
    }

    /** Para incrustarlo en un <img> sin escribir archivos. */
    public static function dataUri(Unidad $unidad, int $tamano = 200): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($unidad, $tamano));
    }
}
