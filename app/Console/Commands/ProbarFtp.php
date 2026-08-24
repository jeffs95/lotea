<?php

namespace App\Console\Commands;

use App\Support\AlmacenDeArchivos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Comprueba que el FTP responde antes de descubrirlo con un carro a medio subir.
 *
 * Sube un archivo mínimo, lo lee, lo borra y dice cuánto tardó cada paso. Es lo
 * primero que hay que correr al configurar un servidor nuevo o al sospechar de
 * la VPN.
 */
class ProbarFtp extends Command
{
    protected $signature = 'lotea:probar-ftp';

    protected $description = 'Comprueba que el disco de archivos responde: escribe, lee y borra';

    public function handle(): int
    {
        $disco = AlmacenDeArchivos::nombreDelDisco();

        $this->line("Disco configurado: <fg=cyan>{$disco}</>");

        if ($disco !== 'ftp_documentos') {
            $this->warn('Los archivos no están yendo al FTP. Se configura con LOTEA_DISCO_ARCHIVOS=ftp_documentos.');
        }

        if ($disco === 'ftp_documentos' && blank(config('filesystems.disks.ftp_documentos.host'))) {
            $this->error('Falta FTP_HOST. Sin eso no hay a dónde conectarse.');

            return self::FAILURE;
        }

        $ruta = 'lotea-prueba/'.now()->format('Ymd-His').'.txt';
        $contenido = 'Prueba de escritura de Lotea.';

        try {
            $this->paso('Escribir', fn () => Storage::disk($disco)->put($ruta, $contenido));

            $leido = $this->paso('Leer', fn () => Storage::disk($disco)->get($ruta));

            if ($leido !== $contenido) {
                $this->error('Lo que se leyó no es lo que se escribió. Revisá el modo pasivo y el root.');

                return self::FAILURE;
            }

            $this->paso('Borrar', fn () => Storage::disk($disco)->delete($ruta));
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('No se pudo: '.$e->getMessage());
            $this->line('Con el FTP de la red interna, el servidor tiene que estar dentro de esa red o con la VPN levantada.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('El disco responde. Las subidas van a funcionar.');

        return self::SUCCESS;
    }

    protected function paso(string $nombre, callable $accion): mixed
    {
        $desde = microtime(true);
        $resultado = $accion();
        $ms = round((microtime(true) - $desde) * 1000);

        $this->line(sprintf('  %-8s <fg=green>ok</> <fg=gray>(%d ms)</>', $nombre, $ms));

        return $resultado;
    }
}
