<?php

namespace App\Support;

use App\Models\Empresa;
use App\Models\Unidad;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

/**
 * El QR que se pega en el parabrisas.
 *
 * En SVG y no en PNG: se imprime nítido a cualquier tamaño y no necesita
 * imagick en el servidor.
 *
 * Lleva el logo del concesionario en el centro cuando lo tiene configurado. Eso
 * no es adorno: una etiqueta con la marca del patio se ve profesional y el
 * comprador entiende de quién es el carro sin escanear. Pero un logo encima
 * tapa módulos del código, así que hay dos cuidados que no se pueden saltar:
 *
 * - Corrección de errores **alta** (recupera hasta un 30% del contenido) en vez
 *   de la media que basta para un QR limpio.
 * - El logo ocupa como máximo un 22% del ancho, con un respiro blanco alrededor.
 *   Más grande empieza a fallar en teléfonos viejos y con poca luz.
 *
 * Los módulos se quedan negros aunque el cliente tenga un color de marca. Un QR
 * de colores se ve bien en pantalla y se lee mal en un parqueo a media luz; la
 * marca ya la pone el logo, que no le quita contraste a nada.
 */
class QrDeUnidad
{
    /** Cuánto del ancho del QR ocupa el logo. Lo lee el test que comprueba
     *  que un código así todavía se puede escanear. */
    public const PROPORCION_DEL_LOGO = 0.22;

    /** El logo se reduce a esto antes de incrustarse, para no inflar el SVG. */
    protected const LADO_DEL_LOGO_EN_PIXELES = 140;

    public static function url(Unidad $unidad): string
    {
        return route('escaneo', ['codigo' => $unidad->codigo_qr]);
    }

    public static function svg(Unidad $unidad, int $tamano = 200, bool $conLogo = true): string
    {
        $empresa = $unidad->empresa;
        $logo = $conLogo ? self::logoIncrustable($empresa) : null;

        $svg = (string) QrCode::format('svg')
            ->size($tamano)
            ->margin(0)
            // Con el logo tapando el centro hace falta el nivel alto; sin él,
            // el medio ya tolera que la etiqueta se ensucie o se raye.
            ->errorCorrection($logo ? 'H' : 'M')
            ->generate(self::url($unidad));

        return $logo ? self::incrustarLogo($svg, $logo, $tamano) : $svg;
    }

    /** Para incrustarlo en un <img> sin escribir archivos. */
    public static function dataUri(Unidad $unidad, int $tamano = 200, bool $conLogo = true): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($unidad, $tamano, $conLogo));
    }

    /**
     * El logo del concesionario como data URI, ya reducido.
     *
     * Tiene que ir embebido y no como enlace: el SVG se sirve dentro de un
     * data URI y ahí una imagen externa no carga nunca.
     */
    protected static function logoIncrustable(?Empresa $empresa): ?string
    {
        if (! $empresa || blank($empresa->logo_path)) {
            return null;
        }

        try {
            $archivo = $empresa->archivoDeMarcaLocal('logo_path');

            if (! $archivo) {
                return null;
            }

            return self::reducir($archivo);
        } catch (Throwable) {
            // Un logo que no se puede leer no puede dejar sin etiqueta a nadie:
            // se imprime el QR sin él.
            return null;
        }
    }

    /** Reduce el logo a un PNG cuadrado sobre blanco, listo para incrustar. */
    protected static function reducir(string $archivo): ?string
    {
        $original = @imagecreatefromstring((string) file_get_contents($archivo));

        if ($original === false) {
            return null;
        }

        $anchoOriginal = imagesx($original);
        $altoOriginal = imagesy($original);
        $lado = self::LADO_DEL_LOGO_EN_PIXELES;

        $lienzo = imagecreatetruecolor($lado, $lado);

        // Fondo blanco: el lector necesita el contraste, y un logo con
        // transparencia sobre los módulos del código sería ilegible.
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));

        // El logo suele ser horizontal: se encaja sin deformarlo.
        $escala = min($lado / $anchoOriginal, $lado / $altoOriginal);
        $ancho = (int) round($anchoOriginal * $escala);
        $alto = (int) round($altoOriginal * $escala);

        imagecopyresampled(
            $lienzo, $original,
            (int) (($lado - $ancho) / 2), (int) (($lado - $alto) / 2),
            0, 0,
            $ancho, $alto,
            $anchoOriginal, $altoOriginal,
        );

        ob_start();
        imagepng($lienzo);
        $png = (string) ob_get_clean();

        imagedestroy($lienzo);
        imagedestroy($original);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /**
     * Pone el logo en el centro, sobre un cuadro blanco con esquinas redondas.
     *
     * El cuadro es lo que salva la lectura: separa el logo de los módulos y le
     * da al lector una zona limpia que puede reconstruir con la corrección de
     * errores.
     */
    protected static function incrustarLogo(string $svg, string $logo, int $tamano): string
    {
        $lado = (int) round($tamano * self::PROPORCION_DEL_LOGO);
        $respiro = (int) round($lado * 0.16);
        $caja = $lado + $respiro * 2;
        $origen = (int) round(($tamano - $caja) / 2);
        $radio = (int) round($caja * 0.18);

        $x = $origen + $respiro;

        $capa = '<rect x="'.$origen.'" y="'.$origen.'" width="'.$caja.'" height="'.$caja
            .'" rx="'.$radio.'" fill="#ffffff"/>'
            .'<image x="'.$x.'" y="'.$x.'" width="'.$lado.'" height="'.$lado
            .'" href="'.$logo.'" preserveAspectRatio="xMidYMid meet"/>';

        return str_replace('</svg>', $capa.'</svg>', $svg);
    }
}
