<?php

namespace App\Console\Commands;

use App\Support\AlmacenDeArchivos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Una copia de la base, fuera del servidor.
 *
 * Importa dónde queda: un respaldo en el mismo disco que la base no es un
 * respaldo, es el mismo archivo dos veces. Si el servidor se pierde —se cae
 * el disco, se borra la máquina, alguien se equivoca de comando— se pierden
 * los dos juntos.
 *
 * Por eso se sube al cubo privado de R2, que ya está pagado, está en otra
 * empresa y no se puede leer desde internet. Las fotos no hacen falta
 * respaldarlas: ya viven ahí.
 */
class RespaldarLaBase extends Command
{
    protected $signature = 'lotea:respaldar
        {--conservar=14 : Cuántos días de copias se guardan}';

    protected $description = 'Vuelca la base de datos y la sube al cubo privado';

    protected const CARPETA = 'respaldos';

    public function handle(): int
    {
        $conexion = config('database.default');
        $base = config("database.connections.{$conexion}");

        if (($base['driver'] ?? null) !== 'pgsql') {
            $this->error('Esto solo sabe respaldar PostgreSQL.');

            return self::FAILURE;
        }

        $nombre = 'lotea-'.now()->format('Y-m-d-His').'.sql.gz';
        $temporal = sys_get_temp_dir().'/'.$nombre;

        try {
            $this->volcar($base, $temporal);
            $this->subir($temporal, $nombre);
            $this->limpiarLosViejos();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            @unlink($temporal);
        }

        return self::SUCCESS;
    }

    /**
     * La contraseña va por el entorno del proceso y no en la línea de
     * comandos: lo segundo la deja escrita en la lista de procesos, donde la
     * ve cualquiera que esté dentro de la máquina.
     *
     * Se encadena con una tubería del shell en vez de con dos procesos de
     * PHP porque el volcado puede pesar cientos de megas: así va de pg_dump a
     * gzip y al disco sin pasar entero por la memoria de PHP.
     */
    protected function volcar(array $base, string $destino): void
    {
        $this->info('Volcando la base…');

        $comando = sprintf(
            'set -o pipefail; %s --host=%s --port=%s --username=%s --dbname=%s '
                .'--no-owner --no-privileges --clean --if-exists | gzip -9 > %s',
            escapeshellarg(config('lotea.pg_dump')),
            escapeshellarg((string) $base['host']),
            escapeshellarg((string) $base['port']),
            escapeshellarg((string) $base['username']),
            escapeshellarg((string) $base['database']),
            escapeshellarg($destino),
        );

        $proceso = Process::fromShellCommandline($comando, timeout: 1800);
        $proceso->setEnv(['PGPASSWORD' => (string) $base['password']]);
        $proceso->run();

        if (! $proceso->isSuccessful()) {
            throw new RuntimeException('pg_dump falló: '.trim($proceso->getErrorOutput()));
        }

        $peso = @filesize($destino);

        // Un gzip vacío pesa unos veinte bytes: si sale eso, el volcado no
        // trajo nada y subirlo sería guardar un respaldo que no respalda.
        if ($peso === false || $peso < 1024) {
            throw new RuntimeException('El volcado salió vacío.');
        }

        $this->line('  '.$this->enMegas($peso));
    }

    protected function subir(string $archivo, string $nombre): void
    {
        $disco = AlmacenDeArchivos::discoPrivado();

        $this->info("Subiendo a «{$disco}»…");

        $puesto = Storage::disk($disco)->putFileAs(self::CARPETA, $archivo, $nombre);

        if ($puesto === false) {
            throw new RuntimeException("No se pudo subir el respaldo al disco «{$disco}».");
        }

        $this->line('  '.self::CARPETA.'/'.$nombre);
    }

    /**
     * Se borran los viejos porque si no el cubo crece para siempre, y lo que
     * sirve de un respaldo son los últimos, no los de hace ocho meses.
     */
    protected function limpiarLosViejos(): void
    {
        $dias = (int) $this->option('conservar');

        if ($dias < 1) {
            return;
        }

        $disco = Storage::disk(AlmacenDeArchivos::discoPrivado());
        $corte = now()->subDays($dias)->getTimestamp();
        $borrados = 0;

        foreach ($disco->files(self::CARPETA) as $archivo) {
            if ($disco->lastModified($archivo) < $corte) {
                $disco->delete($archivo);
                $borrados++;
            }
        }

        if ($borrados > 0) {
            $this->line("  Se borraron {$borrados} respaldos de más de {$dias} días.");
        }
    }

    protected function enMegas(int|false $bytes): string
    {
        if ($bytes === false) {
            return '—';
        }

        return $bytes < 1048576
            ? number_format($bytes / 1024, 1).' KB'
            : number_format($bytes / 1048576, 1).' MB';
    }
}
