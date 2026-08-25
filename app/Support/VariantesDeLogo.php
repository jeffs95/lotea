<?php

namespace App\Support;

use GdImage;
use RuntimeException;

/**
 * Saca del logo del cliente las versiones que hacen falta para usarlo en todos
 * lados sin que se vea mal.
 *
 * Un concesionario entrega un archivo —casi siempre el que usa en Facebook, con
 * su fondo pegado— y ese mismo archivo tiene que servir para la cabecera clara
 * del portal, para el panel en modo oscuro, para el centro de un QR y para la
 * pestaña del navegador. Sin variantes, o se ve un recuadro negro sobre fondo
 * blanco, o el texto blanco desaparece sobre papel.
 *
 * Lo que se produce, con los nombres del oficio:
 *
 * - **isologo**: la marca completa, imagen y texto juntos e inseparables, que
 *   es como viene la mayoría.
 * - **isotipo**: solo el símbolo. Es el que va en el QR y en el favicon, donde
 *   el texto no se leería.
 * - **logotipo**: solo el nombre escrito.
 *
 * Y de cada uno, la versión para fondo claro y para fondo oscuro.
 *
 * Todo sale del archivo original: no se inventa nada ni se redibuja. Lo que se
 * hace es recortar las piezas, quitar el fondo y, para la versión clara,
 * oscurecer lo que era blanco respetando los colores de la marca.
 */
class VariantesDeLogo
{
    /** Un píxel por debajo de esto se considera fondo y se vuelve transparente. */
    protected const UMBRAL_DE_FONDO = 46;

    /** Por encima de esta saturación el color es de la marca y no se toca. */
    protected const SATURACION_DE_COLOR = 0.35;

    /** Margen que se deja alrededor de cada pieza recortada. */
    protected const MARGEN = 12;

    /** Lado del icono de la pestaña. Sirve también para «añadir a inicio». */
    protected const LADO_DEL_FAVICON = 512;

    /**
     * @param  string|null  $colorDeMarca  para el fondo del icono de la pestaña
     * @return array<string, GdImage> nombre de la variante => imagen
     */
    public static function desde(string $archivo, ?string $colorDeMarca = null): array
    {
        $original = @imagecreatefromstring((string) file_get_contents($archivo));

        if ($original === false) {
            throw new RuntimeException("No se pudo leer la imagen «{$archivo}».");
        }

        $piezas = self::separarPiezas($original);

        // El símbolo suele venir muy horizontal, y en un favicon o en el centro
        // de un QR eso se ve diminuto. La versión cuadrada lo centra en un
        // lienzo con aire para que ocupe el espacio que tiene.
        if (isset($piezas['isotipo'])) {
            $piezas['isotipo-cuadrado'] = self::encuadrar($piezas['isotipo']);
        }

        $variantes = [];

        foreach ($piezas as $nombre => $pieza) {
            $variantes[$nombre] = $pieza;
            $variantes[$nombre.'-claro'] = self::paraFondoClaro($pieza);
        }

        if (isset($piezas['isotipo'])) {
            $variantes['favicon'] = self::favicon(
                $piezas['isotipo'],
                $variantes['isotipo-claro'],
                $colorDeMarca,
            );
        }

        imagedestroy($original);

        return $variantes;
    }

    /**
     * Parte el logo en isologo, isotipo y logotipo, ya sin fondo.
     *
     * Las piezas se encuentran solas: se recorren las filas buscando dónde hay
     * tinta y dónde no. Entre el símbolo y el texto siempre hay una franja
     * vacía, y ahí es donde se corta.
     *
     * @return array<string, GdImage>
     */
    protected static function separarPiezas(GdImage $original): array
    {
        $sinFondo = self::quitarFondo($original);
        $bandas = self::bandasConTinta($original);

        $piezas = ['isologo' => self::recortar($sinFondo, self::MARGEN)];

        if (count($bandas) >= 2) {
            // La primera banda es el símbolo; el resto, el nombre.
            $simbolo = array_shift($bandas);
            $texto = [$bandas[0][0], end($bandas)[1]];

            $piezas['isotipo'] = self::recortarBanda($sinFondo, $simbolo);
            $piezas['logotipo'] = self::recortarBanda($sinFondo, $texto);
        }

        return $piezas;
    }

    /**
     * Convierte el fondo en transparencia.
     *
     * El alfa sale de lo oscuro que sea el píxel y no de una comparación
     * exacta: así los bordes suavizados del original siguen suaves en vez de
     * quedar dentados.
     */
    protected static function quitarFondo(GdImage $original): GdImage
    {
        $ancho = imagesx($original);
        $alto = imagesy($original);

        $salida = imagecreatetruecolor($ancho, $alto);
        imagealphablending($salida, false);
        imagesavealpha($salida, true);
        imagefill($salida, 0, 0, imagecolorallocatealpha($salida, 0, 0, 0, 127));

        for ($y = 0; $y < $alto; $y++) {
            for ($x = 0; $x < $ancho; $x++) {
                [$r, $v, $a] = self::rgbEn($original, $x, $y);

                $brillo = max($r, $v, $a);

                if ($brillo <= self::UMBRAL_DE_FONDO) {
                    continue;
                }

                // Del umbral hacia arriba, cuanto más brillante más opaco.
                $opacidad = min(1, ($brillo - self::UMBRAL_DE_FONDO) / (128 - self::UMBRAL_DE_FONDO));
                $alfa = (int) round(127 * (1 - $opacidad));

                imagesetpixel($salida, $x, $y, imagecolorallocatealpha($salida, $r, $v, $a, $alfa));
            }
        }

        return $salida;
    }

    /**
     * La misma pieza, pero legible sobre papel blanco.
     *
     * Lo gris y lo blanco se oscurecen; lo que tiene color —el rojo de la
     * marca— se deja intacto. Invertir todo daría un logo cian y verde.
     */
    protected static function paraFondoClaro(GdImage $pieza): GdImage
    {
        $ancho = imagesx($pieza);
        $alto = imagesy($pieza);

        $salida = imagecreatetruecolor($ancho, $alto);
        imagealphablending($salida, false);
        imagesavealpha($salida, true);
        imagefill($salida, 0, 0, imagecolorallocatealpha($salida, 0, 0, 0, 127));

        for ($y = 0; $y < $alto; $y++) {
            for ($x = 0; $x < $ancho; $x++) {
                $color = imagecolorat($pieza, $x, $y);
                $alfa = ($color >> 24) & 0x7F;

                if ($alfa === 127) {
                    continue;
                }

                $r = ($color >> 16) & 0xFF;
                $v = ($color >> 8) & 0xFF;
                $a = $color & 0xFF;

                if (self::saturacion($r, $v, $a) < self::SATURACION_DE_COLOR) {
                    // Gris o blanco: se oscurece en la misma proporción, sin
                    // llegar al negro puro para que conserve su volumen.
                    $claridad = max($r, $v, $a) / 255;
                    $tono = (int) round(24 + (1 - $claridad) * 90);

                    $r = $v = $a = $tono;
                }

                imagesetpixel($salida, $x, $y, imagecolorallocatealpha($salida, $r, $v, $a, $alfa));
            }
        }

        return $salida;
    }

    /**
     * Las franjas horizontales donde hay tinta, de arriba abajo.
     *
     * @return array<int, array{int, int}>
     */
    protected static function bandasConTinta(GdImage $imagen): array
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $minimo = max(2, (int) ($ancho * 0.004));

        $bandas = [];
        $inicio = null;

        for ($y = 0; $y < $alto; $y++) {
            $conTinta = 0;

            for ($x = 0; $x < $ancho; $x += 2) {
                [$r, $v, $a] = self::rgbEn($imagen, $x, $y);

                if (max($r, $v, $a) > self::UMBRAL_DE_FONDO) {
                    $conTinta++;
                }
            }

            if ($conTinta > $minimo && $inicio === null) {
                $inicio = $y;
            }

            if ($conTinta <= $minimo && $inicio !== null) {
                $bandas[] = [$inicio, $y - 1];
                $inicio = null;
            }
        }

        if ($inicio !== null) {
            $bandas[] = [$inicio, $alto - 1];
        }

        // Las franjas de dos píxeles son ruido de compresión, no partes del logo.
        return array_values(array_filter($bandas, fn (array $b) => $alto * 0.01 < $b[1] - $b[0]));
    }

    /** @param  array{int, int}  $banda */
    protected static function recortarBanda(GdImage $imagen, array $banda): GdImage
    {
        [$desde, $hasta] = $banda;
        $margen = self::MARGEN;

        $alto = imagesy($imagen);
        $desde = max(0, $desde - $margen);
        $hasta = min($alto - 1, $hasta + $margen);

        $franja = imagecreatetruecolor(imagesx($imagen), $hasta - $desde + 1);
        imagealphablending($franja, false);
        imagesavealpha($franja, true);
        imagefill($franja, 0, 0, imagecolorallocatealpha($franja, 0, 0, 0, 127));
        imagecopy($franja, $imagen, 0, 0, 0, $desde, imagesx($imagen), $hasta - $desde + 1);

        $recortada = self::recortar($franja, $margen);
        imagedestroy($franja);

        return $recortada;
    }

    /** Quita el vacío que rodea a la pieza y deja un margen parejo. */
    protected static function recortar(GdImage $imagen, int $margen): GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);

        $x1 = $ancho;
        $y1 = $alto;
        $x2 = 0;
        $y2 = 0;

        for ($y = 0; $y < $alto; $y++) {
            for ($x = 0; $x < $ancho; $x++) {
                if ((imagecolorat($imagen, $x, $y) >> 24 & 0x7F) < 110) {
                    $x1 = min($x1, $x);
                    $y1 = min($y1, $y);
                    $x2 = max($x2, $x);
                    $y2 = max($y2, $y);
                }
            }
        }

        if ($x2 <= $x1 || $y2 <= $y1) {
            return $imagen;
        }

        $x1 = max(0, $x1 - $margen);
        $y1 = max(0, $y1 - $margen);
        $x2 = min($ancho - 1, $x2 + $margen);
        $y2 = min($alto - 1, $y2 + $margen);

        $nuevoAncho = $x2 - $x1 + 1;
        $nuevoAlto = $y2 - $y1 + 1;

        $salida = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagealphablending($salida, false);
        imagesavealpha($salida, true);
        imagefill($salida, 0, 0, imagecolorallocatealpha($salida, 0, 0, 0, 127));
        imagecopy($salida, $imagen, 0, 0, $x1, $y1, $nuevoAncho, $nuevoAlto);

        return $salida;
    }

    /**
     * El icono de la pestaña, con fondo propio.
     *
     * Un símbolo transparente no sirve aquí: la barra del navegador es clara en
     * unos equipos y oscura en otros, y el mismo icono se vuelve invisible en
     * uno de los dos. Con el color de la marca detrás se ve siempre, y de paso
     * el cliente reconoce su pestaña entre veinte.
     */
    protected static function favicon(GdImage $simbolo, GdImage $simboloOscuro, ?string $colorDeMarca): GdImage
    {
        $lado = self::LADO_DEL_FAVICON;
        [$r, $v, $a] = self::aRgb($colorDeMarca ?: '#111827');

        $salida = imagecreatetruecolor($lado, $lado);
        imagealphablending($salida, false);
        imagesavealpha($salida, true);
        imagefill($salida, 0, 0, imagecolorallocate($salida, $r, $v, $a));

        // Sobre un fondo claro el trazo claro desaparece: se elige el que
        // contrasta, igual que con la cabecera de las etiquetas.
        $luminancia = (0.2126 * $r + 0.7152 * $v + 0.0722 * $a) / 255;
        $pieza = $luminancia < 0.55 ? $simbolo : $simboloOscuro;

        // El símbolo ocupa el ancho dejando aire a los lados, como el icono de
        // cualquier aplicación.
        $ancho = (int) round($lado * 0.78);
        $alto = (int) round($ancho * imagesy($pieza) / imagesx($pieza));

        imagealphablending($salida, true);
        imagecopyresampled(
            $salida, $pieza,
            (int) round(($lado - $ancho) / 2), (int) round(($lado - $alto) / 2),
            0, 0,
            $ancho, $alto,
            imagesx($pieza), imagesy($pieza),
        );

        imagealphablending($salida, false);

        return self::redondearEsquinas($salida, (int) round($lado * 0.22));
    }

    /** Le quita las esquinas al icono, que es como se ven todos hoy. */
    protected static function redondearEsquinas(GdImage $imagen, int $radio): GdImage
    {
        $lado = imagesx($imagen);
        $transparente = imagecolorallocatealpha($imagen, 0, 0, 0, 127);

        for ($y = 0; $y < $lado; $y++) {
            for ($x = 0; $x < $lado; $x++) {
                // Centro del círculo de la esquina más cercana.
                $cx = $x < $radio ? $radio : ($x > $lado - $radio ? $lado - $radio : $x);
                $cy = $y < $radio ? $radio : ($y > $lado - $radio ? $lado - $radio : $y);

                if (($x - $cx) ** 2 + ($y - $cy) ** 2 > $radio ** 2) {
                    imagesetpixel($imagen, $x, $y, $transparente);
                }
            }
        }

        return $imagen;
    }

    /** @return array{int, int, int} */
    protected static function aRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Centra la pieza en un lienzo cuadrado.
     *
     * Para el favicon y para el centro del QR: ahí el espacio es cuadrado y una
     * pieza alargada quedaría de dos píxeles de alto.
     */
    protected static function encuadrar(GdImage $pieza): GdImage
    {
        $ancho = imagesx($pieza);
        $alto = imagesy($pieza);
        $lado = max($ancho, $alto);

        $salida = imagecreatetruecolor($lado, $lado);
        imagealphablending($salida, false);
        imagesavealpha($salida, true);
        imagefill($salida, 0, 0, imagecolorallocatealpha($salida, 0, 0, 0, 127));

        imagecopy(
            $salida, $pieza,
            (int) round(($lado - $ancho) / 2), (int) round(($lado - $alto) / 2),
            0, 0,
            $ancho, $alto,
        );

        return $salida;
    }

    /** @return array{int, int, int} */
    protected static function rgbEn(GdImage $imagen, int $x, int $y): array
    {
        $color = imagecolorat($imagen, $x, $y);

        return [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF];
    }

    /** Cuánto color tiene el píxel: 0 es gris puro, 1 es color saturado. */
    protected static function saturacion(int $r, int $v, int $a): float
    {
        $maximo = max($r, $v, $a);

        return $maximo === 0 ? 0.0 : ($maximo - min($r, $v, $a)) / $maximo;
    }

    /** Guarda una variante como PNG con transparencia. */
    public static function guardar(GdImage $imagen, string $destino): void
    {
        file_put_contents($destino, self::aPng($imagen));
    }

    /** El PNG en memoria, para mandarlo al disco de archivos sin pasar por /tmp. */
    public static function aPng(GdImage $imagen): string
    {
        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);

        ob_start();
        imagepng($imagen);

        return (string) ob_get_clean();
    }
}
