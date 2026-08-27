<?php

namespace App\Console\Commands;

use App\Support\AlmacenDeArchivos;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Reencuentra los archivos que la base ya no sabe dónde están.
 *
 * La ruta de un archivo lleva dentro el id de su registro y el de la unidad, y
 * esos ids no son los mismos en dos bases distintas. Así que un archivo subido
 * en una máquina y una fila que lo describe en otra no se encuentran nunca,
 * aunque los dos existan: el archivo está en
 * «unidades/3/fotos/7/ABC.webp» y la fila lo busca en
 * «unidades/1/fotos/1/ABC.webp».
 *
 * Pasa al mover una instalación de sitio, al migrar de almacenamiento desde dos
 * lados, o al restaurar una base sobre archivos que ya estaban. El síntoma es
 * una ficha sin fotos con los archivos ahí mismo, intactos.
 *
 * Esto los reencuentra por su nombre, que sí es único y no cambia, y los deja
 * donde la base los busca. No borra el original: si algo sale mal, el archivo
 * viejo sigue donde estaba.
 */
class ReubicarArchivos extends Command
{
    protected $signature = 'lotea:reubicar-archivos
        {--fingir : Muestra qué haría, sin tocar nada}';

    protected $description = 'Busca por nombre los archivos que su registro ya no encuentra y los deja en su sitio';

    /** El de caché no cuenta: es una copia, no el sitio donde el archivo vive. */
    protected const NO_BUSCAR_EN = [AlmacenDeArchivos::DISCO_CACHE];

    /** @var array<string, array<string, string>> disco => [nombre => ruta] */
    protected array $indice = [];

    public function handle(): int
    {
        $fingir = (bool) $this->option('fingir');

        $huerfanos = $this->buscarHuerfanos();

        if ($huerfanos === []) {
            $this->info('Todos los archivos están donde su registro dice.');

            return self::SUCCESS;
        }

        $this->line('Registros que no encuentran su archivo: <fg=yellow>'.count($huerfanos).'</>');
        $this->newLine();

        $reubicados = 0;
        $perdidos = [];

        foreach ($huerfanos as $media) {
            $encontrado = $this->buscarPorNombre($media->file_name);

            if ($encontrado === null) {
                $perdidos[] = "  #{$media->getKey()} {$media->file_name}: no aparece en ningún disco";

                continue;
            }

            [$discoOrigen, $rutaOrigen] = $encontrado;
            $discoDestino = $this->discoQueLeToca($media);
            $rutaDestino = AlmacenDeArchivos::rutaDe($media);

            $this->line("  <fg=green>{$media->file_name}</>");
            $this->line("    de  {$discoOrigen}:{$rutaOrigen}");
            $this->line("    a   {$discoDestino}:{$rutaDestino}");

            if ($fingir) {
                $reubicados++;

                continue;
            }

            try {
                $this->copiar($discoOrigen, $rutaOrigen, $discoDestino, $rutaDestino);
                $this->copiarConversiones($media, $discoOrigen, $rutaOrigen, $discoDestino);

                $media->forceFill([
                    'disk' => $discoDestino,
                    'conversions_disk' => $discoDestino,
                ])->save();

                $reubicados++;
            } catch (Throwable $e) {
                $perdidos[] = "  #{$media->getKey()} {$media->file_name}: ".$e->getMessage();
            }
        }

        $this->newLine();

        if ($fingir) {
            $this->comment("Se reubicarían {$reubicados}. Nada se tocó; quite --fingir para hacerlo.");

            return self::SUCCESS;
        }

        $this->info("Reubicados: {$reubicados}");

        if ($perdidos !== []) {
            $this->newLine();
            $this->warn('Sin resolver '.count($perdidos).':');

            foreach (array_slice($perdidos, 0, 10) as $linea) {
                $this->line($linea);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return array<int, Media> */
    protected function buscarHuerfanos(): array
    {
        // Sin filtro de empresa: esto es mantenimiento de toda la instalación.
        return Tenancy::sinFiltro(fn () => Media::query()->get()
            ->filter(function (Media $media) {
                try {
                    return ! Storage::disk($media->disk)->exists(AlmacenDeArchivos::rutaDe($media));
                } catch (Throwable) {
                    return true;
                }
            })
            ->values()
            ->all());
    }

    /**
     * El archivo, esté donde esté, buscado por su nombre.
     *
     * El nombre es un ULID que medialibrary no repite, así que encontrar uno
     * igual en otro disco es encontrar el mismo archivo y no un tocayo.
     *
     * @return array{0: string, 1: string}|null
     */
    protected function buscarPorNombre(string $nombre): ?array
    {
        foreach ($this->discosDondeBuscar() as $disco) {
            $indice = $this->indiceDe($disco);

            if (isset($indice[$nombre])) {
                return [$disco, $indice[$nombre]];
            }
        }

        return null;
    }

    /**
     * Todos los discos configurados, empezando por donde deberían estar.
     *
     * Se miran todos y no una lista escrita a mano: el día que se agregue un
     * almacenamiento nuevo, este comando lo tiene que encontrar sin que nadie
     * se acuerde de venir a apuntarlo aquí.
     *
     * @return array<int, string>
     */
    protected function discosDondeBuscar(): array
    {
        $preferidos = [AlmacenDeArchivos::discoPublico(), AlmacenDeArchivos::discoPrivado()];
        $todos = array_keys((array) config('filesystems.disks', []));

        return array_values(array_diff(
            array_unique([...$preferidos, ...$todos]),
            self::NO_BUSCAR_EN,
        ));
    }

    /**
     * Lo que hay en un disco, por nombre de archivo.
     *
     * Se recorre una vez y se recuerda: preguntarle al FTP por cada archivo
     * sería un viaje de red por registro.
     *
     * @return array<string, string>
     */
    protected function indiceDe(string $disco): array
    {
        if (isset($this->indice[$disco])) {
            return $this->indice[$disco];
        }

        $this->indice[$disco] = [];

        if (blank(config("filesystems.disks.{$disco}"))) {
            return [];
        }

        try {
            foreach (Storage::disk($disco)->allFiles() as $ruta) {
                // Las conversiones se resuelven aparte, a partir del original.
                if (str_contains($ruta, '/conversions/')) {
                    continue;
                }

                $this->indice[$disco][basename($ruta)] = $ruta;
            }
        } catch (Throwable $e) {
            $this->line("  <fg=gray>no se pudo leer {$disco}: {$e->getMessage()}</>");
        }

        return $this->indice[$disco];
    }

    protected function copiar(string $discoOrigen, string $rutaOrigen, string $discoDestino, string $rutaDestino): void
    {
        $contenido = Storage::disk($discoOrigen)->get($rutaOrigen);

        if ($contenido === null) {
            throw new \RuntimeException("no se pudo leer {$discoOrigen}:{$rutaOrigen}");
        }

        Storage::disk($discoDestino)->put($rutaDestino, $contenido);
    }

    /** Las miniaturas viven junto al original, en su propia carpeta. */
    protected function copiarConversiones(Media $media, string $discoOrigen, string $rutaOrigen, string $discoDestino): void
    {
        $carpetaOrigen = dirname($rutaOrigen).'/conversions';
        $carpetaDestino = dirname(AlmacenDeArchivos::rutaDe($media)).'/conversions';

        try {
            $archivos = Storage::disk($discoOrigen)->files($carpetaOrigen);
        } catch (Throwable) {
            return;
        }

        foreach ($archivos as $archivo) {
            $this->copiar($discoOrigen, $archivo, $discoDestino, $carpetaDestino.'/'.basename($archivo));
        }
    }

    protected function discoQueLeToca(Media $media): string
    {
        return $media->collection_name === 'fotos'
            ? AlmacenDeArchivos::discoPublico()
            : AlmacenDeArchivos::discoPrivado();
    }
}
