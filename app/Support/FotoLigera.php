<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Throwable;

/**
 * Las fotos se guardan pesando lo que tienen que pesar.
 *
 * El formulario ya las encoge a 1920 px en el navegador, pero encoger no es
 * comprimir: reescala y vuelve a guardar con calidad casi máxima, así que del
 * celular salen tres megas y tres megas llegan. Medido con una foto de ese
 * tamaño: 2,960 KB al salir del navegador contra 839 KB recomprimida. El 72%
 * de lo que se paga de almacenamiento es calidad que nadie distingue en la
 * pantalla de un carro.
 *
 * Importa por lo que se acumula: las fotos de los carros ya vendidos se quedan
 * guardadas, y con treinta concesionarios adentro el cubo crece todos los días
 * aunque nadie suba nada nuevo.
 */
class FotoLigera
{
    /** Más ancho que esto no sirve para mirar un carro en el portal. */
    public const ANCHO_MAXIMO = 2000;

    public const CALIDAD = 82;

    /**
     * Por debajo de esto no se toca.
     *
     * Volver a comprimir algo que ya está liviano no ahorra nada y sí le quita
     * calidad: cada pasada de JPEG pierde un poco más.
     */
    public const PESO_QUE_VALE_LA_PENA = 600 * 1024;

    /**
     * Lo que se sabe comprimir, por lo que el archivo es y no por cómo se
     * llama.
     *
     * Mirar la extensión no sirve: al bajar un archivo del cubo para
     * procesarlo queda en un temporal sin extensión ninguna, y ahí la librería
     * no sabe en qué formato guardarlo. Eso ya rompió una vez el comando que
     * comprime lo guardado, en silencio y sin cambiar un solo archivo.
     */
    protected const FORMATOS = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
    ];

    /** Lo que viene del formulario, que puede ser uno o treinta. */
    public static function encoger(mixed $subidas): void
    {
        foreach (Arr::wrap($subidas) as $subida) {
            if (! $subida instanceof TemporaryUploadedFile) {
                continue;
            }

            $ruta = $subida->getRealPath();

            if (is_string($ruta) && $ruta !== '') {
                static::encogerArchivo($ruta);
            }
        }
    }

    /**
     * Para la subida: que falle una foto no puede tumbar el formulario.
     *
     * En el peor caso se guarda como venía, que es justo lo que pasaba antes
     * de que esto existiera.
     */
    public static function encogerArchivo(string $ruta): bool
    {
        try {
            return static::encogerArchivoOFallar($ruta);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Para los comandos, que sí quieren enterarse.
     *
     * Devuelve true si el archivo quedó más liviano; false si no hacía falta
     * tocarlo. Si algo sale mal, lanza: un comando que comprime mil fotos y
     * dice «cero liberados» sin explicar por qué no sirve de nada.
     */
    public static function encogerArchivoOFallar(string $ruta): bool
    {
        if (! is_file($ruta)) {
            return false;
        }

        $pesaba = filesize($ruta);
        $medidas = @getimagesize($ruta);

        if ($pesaba === false || $medidas === false) {
            return false;
        }

        $formato = static::FORMATOS[$medidas[2]] ?? null;

        if ($formato === null || ! static::valeLaPena($medidas, $pesaba)) {
            return false;
        }

        Image::load($ruta)
            ->fit(Fit::Max, static::ANCHO_MAXIMO, static::ANCHO_MAXIMO)
            ->quality(static::CALIDAD)
            ->format($formato)
            ->save($ruta);

        clearstatcache(true, $ruta);

        $pesa = filesize($ruta);

        if ($pesa === false) {
            throw new RuntimeException("el archivo desapareció al comprimirlo: {$ruta}");
        }

        return $pesa < $pesaba;
    }

    /**
     * Se trabaja si pesa de más o si mide de más.
     *
     * Las dos condiciones hacen falta: una foto de 1920 px no pasa del ancho y
     * aun así llega con tres megas, que es el caso corriente.
     *
     * @param  array{0: int, 1: int}  $medidas
     */
    protected static function valeLaPena(array $medidas, int $peso): bool
    {
        return $peso > static::PESO_QUE_VALE_LA_PENA
            || $medidas[0] > static::ANCHO_MAXIMO
            || $medidas[1] > static::ANCHO_MAXIMO;
    }
}
