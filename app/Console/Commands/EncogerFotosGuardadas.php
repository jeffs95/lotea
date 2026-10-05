<?php

namespace App\Console\Commands;

use App\Support\FotoLigera;
use App\Support\RutaDeArchivos;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Comprime las fotos que ya estaban guardadas.
 *
 * Las nuevas entran livianas desde que el formulario las comprime al llegar,
 * pero las que ya están en el cubo siguen pesando lo que pesaban: tres megas
 * donde bastan ochocientos kilos. Y son las que más se acumulan, porque las
 * fotos de un carro vendido no se borran nunca.
 *
 * Se toca únicamente el original. Las conversiones —la del portal y la
 * miniatura— ya salieron del archivo grande y se ven igual, así que
 * regenerarlas sería gastar el doble para no cambiar nada.
 *
 * Con --fingir dice cuánto se ahorraría sin escribir una sola vez, que es como
 * conviene correrlo la primera vez contra producción.
 */
class EncogerFotosGuardadas extends Command
{
    protected $signature = 'lotea:encoger-fotos
        {--fingir : Calcula el ahorro sin tocar nada}
        {--coleccion=* : Solo estas colecciones (por defecto, las de fotos)}';

    protected $description = 'Comprime los originales de las fotos ya guardadas y dice cuánto espacio libera';

    /** Las de volumen. Los documentos y el DPI se quedan como están. */
    protected const COLECCIONES = ['fotos', 'fotos_subasta'];

    public function handle(): int
    {
        $fingir = (bool) $this->option('fingir');
        $colecciones = $this->option('coleccion') ?: self::COLECCIONES;

        $fotos = Tenancy::sinFiltro(
            fn () => Media::whereIn('collection_name', $colecciones)->get()
        );

        if ($fotos->isEmpty()) {
            $this->info('No hay fotos en '.implode(', ', $colecciones).'.');

            return self::SUCCESS;
        }

        $this->info($fingir
            ? "Calculando el ahorro de {$fotos->count()} fotos. No se va a escribir nada."
            : "Comprimiendo {$fotos->count()} fotos.");

        $barra = $this->output->createProgressBar($fotos->count());
        $barra->start();

        $antes = 0;
        $despues = 0;
        $tocadas = 0;
        $fallos = 0;

        foreach ($fotos as $foto) {
            try {
                [$pesaba, $pesa] = $this->procesar($foto, $fingir);

                $antes += $pesaba;
                $despues += $pesa;

                if ($pesa < $pesaba) {
                    $tocadas++;
                }
            } catch (Throwable $e) {
                $fallos++;
                $this->newLine();
                $this->warn("  Foto {$foto->getKey()}: {$e->getMessage()}");
            }

            $barra->advance();
        }

        $barra->finish();
        $this->newLine(2);

        $this->resumir($antes, $despues, $tocadas, $fallos, $fingir);

        return $fallos > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Baja el archivo, lo comprime aparte y solo lo sube si quedó más chico.
     *
     * @return array{0: int, 1: int} lo que pesaba y lo que pesa
     */
    protected function procesar(Media $foto, bool $fingir): array
    {
        $disco = Storage::disk($foto->disk);
        $ruta = (new RutaDeArchivos)->getPath($foto).$foto->file_name;

        if (! $disco->exists($ruta)) {
            throw new \RuntimeException("no está en el disco ({$ruta})");
        }

        $temporal = tempnam(sys_get_temp_dir(), 'encoger');
        file_put_contents($temporal, $disco->get($ruta));

        try {
            $pesaba = filesize($temporal);

            if (! FotoLigera::encogerArchivoOFallar($temporal)) {
                return [$pesaba, $pesaba];
            }

            clearstatcache(true, $temporal);
            $pesa = filesize($temporal);

            if (! $fingir) {
                $disco->put($ruta, file_get_contents($temporal));

                $foto->size = $pesa;
                $foto->saveQuietly();
            }

            return [$pesaba, $pesa];
        } finally {
            @unlink($temporal);
        }
    }

    protected function resumir(int $antes, int $despues, int $tocadas, int $fallos, bool $fingir): void
    {
        $ahorro = $antes - $despues;
        $porcentaje = $antes > 0 ? round($ahorro / $antes * 100) : 0;

        $this->table(['', ''], [
            ['Fotos comprimidas', $tocadas],
            ['Pesaban', $this->enMegas($antes)],
            [$fingir ? 'Pesarían' : 'Pesan', $this->enMegas($despues)],
            [$fingir ? 'Se liberarían' : 'Se liberaron', $this->enMegas($ahorro)." ({$porcentaje}%)"],
        ]);

        if ($fallos > 0) {
            $this->warn("{$fallos} fotos no se pudieron procesar.");
        }

        if ($fingir && $tocadas > 0) {
            $this->info('Corré el mismo comando sin --fingir para aplicarlo.');
        }
    }

    protected function enMegas(int $bytes): string
    {
        return number_format($bytes / 1048576, 1).' MB';
    }
}
