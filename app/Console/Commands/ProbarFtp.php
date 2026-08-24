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

        if ($disco === 'ftp_documentos' && ! $this->asegurarLaRaiz()) {
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

            $this->paso('Borrar', function () use ($disco, $ruta) {
                Storage::disk($disco)->delete($ruta);

                // Borrar el archivo deja la carpeta: sin esto, cada prueba
                // suma un directorio vacío al FTP.
                return Storage::disk($disco)->deleteDirectory(dirname($ruta));
            });
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

    /**
     * Crea la carpeta raíz de Lotea si todavía no está.
     *
     * Flysystem no crea su propio root: si FTP_ROOT apunta a una carpeta que no
     * existe, la primera subida falla con «creating parent directory failed» y
     * nadie entiende por qué. Este FTP lo comparte toda la DGT —tiene ahí
     * EXPEDIENTE, KARDEX, PERMISOS y una docena más— así que la carpeta propia
     * no es un lujo.
     */
    protected function asegurarLaRaiz(): bool
    {
        $config = config('filesystems.disks.ftp_documentos');
        $raiz = trim((string) $config['root'], '/');

        if ($raiz === '') {
            $this->warn('FTP_ROOT está vacío: Lotea escribiría en la raíz compartida del FTP.');

            return true;
        }

        $conexion = @ftp_connect($config['host'], (int) $config['port'], (int) $config['timeout']);

        if (! $conexion || ! @ftp_login($conexion, $config['username'], $config['password'])) {
            $this->error('No se pudo entrar al FTP. Revisá host, usuario y contraseña.');

            return false;
        }

        @ftp_pasv($conexion, (bool) $config['passive']);

        if (@ftp_chdir($conexion, '/'.$raiz)) {
            $this->line("  Carpeta   <fg=green>/{$raiz}</> ya existe");
            @ftp_close($conexion);

            return true;
        }

        $creada = @ftp_mkdir($conexion, '/'.$raiz) !== false;

        $creada
            ? $this->line("  Carpeta   <fg=green>/{$raiz}</> creada")
            : $this->error("No existe /{$raiz} y el usuario no la puede crear. Pedila al administrador del FTP.");

        @ftp_close($conexion);

        return $creada;
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
