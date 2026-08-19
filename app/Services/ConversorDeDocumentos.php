<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Deja cualquier archivo listo para mandárselo al modelo.
 *
 * Las fotos se usan tal cual (reducidas si vienen enormes, que es lo normal
 * con un celular moderno) y los PDF se convierten a imagen con pdftoppm.
 */
class ConversorDeDocumentos
{
    /** Ancho máximo que se manda: más que esto es pagar tokens de más. */
    public const ANCHO_MAXIMO = 1600;

    /** Un documento de más de dos páginas casi nunca aporta datos nuevos. */
    public const PAGINAS_MAXIMAS = 2;

    /**
     * Tope de imágenes que se manda en una lectura.
     *
     * Cada una son tokens que se pagan, y más de esto no aporta: entre el
     * título, la tarjeta y la hoja de subasta ya está todo.
     */
    public const IMAGENES_MAXIMAS = 6;

    /**
     * Junta varios documentos en una sola lista de imágenes.
     *
     * @param  array<int, string>  $rutas
     * @return array<int, string>
     */
    public function variosAImagenes(array $rutas): array
    {
        $imagenes = [];

        foreach ($rutas as $ruta) {
            foreach ($this->aImagenes($ruta) as $imagen) {
                $imagenes[] = $imagen;

                if (count($imagenes) >= self::IMAGENES_MAXIMAS) {
                    return $imagenes;
                }
            }
        }

        return $imagenes;
    }

    /** @return array<int, string> rutas de imágenes temporales */
    public function aImagenes(string $ruta): array
    {
        if (! File::exists($ruta)) {
            return [];
        }

        $tipo = File::mimeType($ruta) ?: '';

        return match (true) {
            str_starts_with($tipo, 'image/') => array_filter([$this->prepararImagen($ruta)]),
            $tipo === 'application/pdf' => $this->desdePdf($ruta),
            default => [],
        };
    }

    public function puedeLeerPdf(): bool
    {
        return $this->rutaDePdftoppm() !== null;
    }

    /** Reduce la foto si viene grande. Una tarjeta se lee bien a 1600px. */
    protected function prepararImagen(string $ruta): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return $ruta;
        }

        $original = @imagecreatefromstring(File::get($ruta));

        if ($original === false) {
            return null;
        }

        $ancho = imagesx($original);

        if ($ancho <= self::ANCHO_MAXIMO) {
            imagedestroy($original);

            return $ruta;
        }

        $alto = (int) round(imagesy($original) * (self::ANCHO_MAXIMO / $ancho));
        $reducida = imagescale($original, self::ANCHO_MAXIMO, $alto);
        imagedestroy($original);

        if ($reducida === false) {
            return $ruta;
        }

        $destino = $this->rutaTemporal('jpg');
        imagejpeg($reducida, $destino, 88);
        imagedestroy($reducida);

        return $destino;
    }

    /** @return array<int, string> */
    protected function desdePdf(string $ruta): array
    {
        $pdftoppm = $this->rutaDePdftoppm();

        if ($pdftoppm === null) {
            return [];
        }

        $prefijo = $this->rutaTemporal('');
        $comando = sprintf(
            '%s -jpeg -r 150 -f 1 -l %d -scale-to %d %s %s 2>/dev/null',
            escapeshellcmd($pdftoppm),
            self::PAGINAS_MAXIMAS,
            self::ANCHO_MAXIMO,
            escapeshellarg($ruta),
            escapeshellarg($prefijo),
        );

        exec($comando, $salida, $codigo);

        if ($codigo !== 0) {
            return [];
        }

        return array_values(array_slice(glob($prefijo.'*.jpg') ?: [], 0, self::PAGINAS_MAXIMAS));
    }

    protected function rutaDePdftoppm(): ?string
    {
        foreach (['/opt/homebrew/bin/pdftoppm', '/usr/local/bin/pdftoppm', '/usr/bin/pdftoppm'] as $candidato) {
            if (is_executable($candidato)) {
                return $candidato;
            }
        }

        $encontrado = trim((string) shell_exec('command -v pdftoppm 2>/dev/null'));

        return $encontrado !== '' ? $encontrado : null;
    }

    protected function rutaTemporal(string $extension): string
    {
        $directorio = storage_path('app/lecturas');

        File::ensureDirectoryExists($directorio);

        return $directorio.'/'.Str::random(20).($extension ? '.'.$extension : '');
    }
}
