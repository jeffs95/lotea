<?php

namespace App\Console\Commands;

use App\Support\AlmacenDeArchivos;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Mueve al disco actual las fotos y documentos que quedaron en otro.
 *
 * Cambiar LOTEA_DISCO_ARCHIVOS solo dice dónde se guarda de ahora en adelante:
 * lo que ya estaba subido se queda donde estaba, y el sistema lo busca en el
 * disco nuevo y no lo encuentra. La ficha se ve sin fotos y el portal muestra
 * las tarjetas vacías.
 *
 * Esto lo arregla: recorre lo que está en el disco viejo y lo pasa al nuevo,
 * regenerando las conversiones. Es idempotente: lo que ya está en su sitio se
 * salta.
 */
class MigrarArchivos extends Command
{
    protected $signature = 'lotea:migrar-archivos
        {--desde= : El disco donde están hoy (por defecto, cualquiera distinto del actual)}
        {--fingir : Muestra qué haría, sin tocar nada}';

    protected $description = 'Pasa las fotos y documentos ya subidos al disco de archivos configurado';

    public function handle(): int
    {
        $destino = AlmacenDeArchivos::nombreDelDisco();
        $desde = $this->option('desde');
        $fingir = (bool) $this->option('fingir');

        $this->line("Disco de destino: <fg=cyan>{$destino}</>");

        // Sin filtro de empresa: esto es mantenimiento de toda la instalación.
        $pendientes = Tenancy::sinFiltro(fn () => Media::query()
            ->when($desde, fn ($q) => $q->where('disk', $desde))
            ->where('disk', '!=', $destino)
            ->get());

        if ($pendientes->isEmpty()) {
            $this->info('No hay nada que mover: todo está en el disco configurado.');

            return self::SUCCESS;
        }

        $this->line("Archivos por mover: <fg=yellow>{$pendientes->count()}</>");
        $this->newLine();

        if ($fingir) {
            foreach ($pendientes->groupBy('disk') as $disco => $grupo) {
                $this->line("  desde <fg=yellow>{$disco}</>: {$grupo->count()} archivos");
            }

            $this->newLine();
            $this->comment('Nada se tocó. Quite --fingir para hacerlo de verdad.');

            return self::SUCCESS;
        }

        $movidos = 0;
        $fallidos = [];

        $barra = $this->output->createProgressBar($pendientes->count());
        $barra->start();

        foreach ($pendientes as $media) {
            try {
                Tenancy::sinFiltro(function () use ($media, $destino) {
                    $modelo = $media->model;

                    if (! $modelo) {
                        throw new \RuntimeException('el registro dueño del archivo ya no existe');
                    }

                    $media->move($modelo, $media->collection_name, $destino);
                });

                $movidos++;
            } catch (Throwable $e) {
                $fallidos[] = "  #{$media->getKey()} ({$media->file_name}): ".$e->getMessage();
            }

            $barra->advance();
        }

        $barra->finish();
        $this->newLine(2);

        $this->info("Movidos: {$movidos}");

        if ($fallidos !== []) {
            $this->newLine();
            $this->warn('No se pudieron mover '.count($fallidos).':');

            foreach (array_slice($fallidos, 0, 10) as $linea) {
                $this->line($linea);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
