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
     * El mismo código, pero para escribirlo dentro del HTML.
     *
     * Y no en un <img src="data:...">, que es como estaba: un SVG cargado así
     * el navegador lo trata como un documento aparte y lo dibuja por su cuenta,
     * y este lleva el logo del concesionario incrustado dentro. Ese dibujo
     * anidado es justo lo que el motor de impresión de Windows no rasteriza, y
     * la etiqueta salía sin código. En línea es un nodo más de la página y se
     * imprime con todo lo demás.
     */
    public static function svgEnLinea(Unidad $unidad, int $tamano = 200, string $clases = '', bool $conLogo = true): string
    {
        $svg = self::svg($unidad, $tamano, $conLogo);

        // Dentro de un documento HTML la declaración XML no va.
        $svg = preg_replace('/^\s*<\?xml[^>]*\?>\s*/', '', $svg) ?? $svg;

        $atributos = 'role="img" aria-label="Código '.e($unidad->codigo_qr).'"';

        if ($clases !== '') {
            $atributos .= ' class="'.e($clases).'"';
        }

        return preg_replace('/<svg /', '<svg '.$atributos.' ', $svg, 1) ?? $svg;
    }

    /**
     * El logo del concesionario como data URI, ya reducido.
     *
     * Tiene que ir embebido y no como enlace: el SVG se sirve dentro de un
     * data URI y ahí una imagen externa no carga nunca.
     */
    protected static function logoIncrustable(?Empresa $empresa): ?array
    {
        // El símbolo antes que la marca completa: en un cuadro de dos
        // centímetros el nombre no se lee y solo le roba sitio al dibujo.
        $campo = filled($empresa?->isotipo_path) ? 'isotipo_path' : 'logo_path';

        if (! $empresa || blank($empresa->{$campo})) {
            return null;
        }

        try {
            $archivo = $empresa->archivoDeMarcaLocal($campo);

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

    /**
     * Reduce el logo sobre fondo blanco, conservando su proporción.
     *
     * Sin forzarlo a cuadrado: un logo apaisado metido en un cuadrado obliga a
     * una caja blanca cuadrada, que tapa módulos de más y encima deja el logo
     * pequeño. Guardando la proporción, la caja se ajusta al logo.
     *
     * @return array{uri: string, proporcion: float}|null
     */
    protected static function reducir(string $archivo): ?array
    {
        $original = @imagecreatefromstring((string) file_get_contents($archivo));

        if ($original === false) {
            return null;
        }

        $anchoOriginal = imagesx($original);
        $altoOriginal = imagesy($original);

        // Un logo muy alargado se recorta al ancho: más plano que 4:1 dejaría
        // una franja tan baja que no se distinguiría nada.
        $proporcion = min(4.0, max(1.0, $anchoOriginal / max(1, $altoOriginal)));

        $ancho = self::LADO_DEL_LOGO_EN_PIXELES;
        $alto = (int) round($ancho / $proporcion);

        $lienzo = imagecreatetruecolor($ancho, $alto);

        // Fondo blanco: el lector necesita el contraste, y un logo con
        // transparencia sobre los módulos del código sería ilegible.
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
        imagealphablending($lienzo, true);

        // Se encaja dentro sin deformarlo.
        $escala = min($ancho / $anchoOriginal, $alto / $altoOriginal);
        $dibujoAncho = (int) round($anchoOriginal * $escala);
        $dibujoAlto = (int) round($altoOriginal * $escala);

        imagecopyresampled(
            $lienzo, $original,
            (int) (($ancho - $dibujoAncho) / 2), (int) (($alto - $dibujoAlto) / 2),
            0, 0,
            $dibujoAncho, $dibujoAlto,
            $anchoOriginal, $altoOriginal,
        );

        ob_start();
        imagepng($lienzo);
        $png = (string) ob_get_clean();

        imagedestroy($lienzo);
        imagedestroy($original);

        return [
            'uri' => 'data:image/png;base64,'.base64_encode($png),
            'proporcion' => $proporcion,
        ];
    }

    /**
     * Pone el logo en el centro, sobre un cuadro blanco con esquinas redondas.
     *
     * El cuadro es lo que salva la lectura: separa el logo de los módulos y le
     * da al lector una zona limpia que puede reconstruir con la corrección de
     * errores.
     */
    /**
     * @param  array{uri: string, proporcion: float}  $logo
     */
    protected static function incrustarLogo(string $svg, array $logo, int $tamano): string
    {
        // El ancho manda; el alto sale de la proporción del logo. Así una marca
        // apaisada ocupa una franja ancha y baja en vez de un cuadrado, y tapa
        // menos módulos para el mismo tamaño aparente.
        $ancho = (int) round($tamano * self::PROPORCION_DEL_LOGO * 1.35);
        $alto = (int) round($ancho / $logo['proporcion']);

        $respiro = (int) round($tamano * 0.03);
        $cajaAncho = $ancho + $respiro * 2;
        $cajaAlto = $alto + $respiro * 2;

        $cajaX = (int) round(($tamano - $cajaAncho) / 2);
        $cajaY = (int) round(($tamano - $cajaAlto) / 2);
        $radio = (int) round(min($cajaAncho, $cajaAlto) * 0.22);

        $capa = '<rect x="'.$cajaX.'" y="'.$cajaY.'" width="'.$cajaAncho.'" height="'.$cajaAlto
            .'" rx="'.$radio.'" fill="#ffffff"/>'
            .'<image x="'.($cajaX + $respiro).'" y="'.($cajaY + $respiro)
            .'" width="'.$ancho.'" height="'.$alto
            .'" href="'.$logo['uri'].'" preserveAspectRatio="xMidYMid meet"/>';

        return str_replace('</svg>', $capa.'</svg>', $svg);
    }
}
