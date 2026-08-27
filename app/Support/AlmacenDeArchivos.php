<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * De dónde salen los bytes de una foto o un documento.
 *
 * En producción viven en el FTP. Pedirlos ahí en cada visita al catálogo sería
 * insostenible —veinte carros con tres fotos cada uno son sesenta lecturas por
 * visitante— así que la primera lectura deja una copia local y las siguientes
 * salen de ahí.
 *
 * La copia local no es la fuente de verdad: se puede borrar entera y se vuelve
 * a llenar sola. El FTP es el único lugar donde el archivo existe de verdad.
 */
class AlmacenDeArchivos
{
    public const DISCO_CACHE = 'cache_archivos';

    /** El disco donde de verdad viven los archivos. */
    public static function disco(): Filesystem
    {
        return Storage::disk(static::nombreDelDisco());
    }

    public static function nombreDelDisco(): string
    {
        return config('media-library.disk_name');
    }

    /**
     * El disco de las fotos del catálogo, que se sirven por CDN.
     *
     * En desarrollo y en los tests los dos discos son el mismo; la distinción
     * la hace el código y no la infraestructura, así que nada se rompe cuando
     * no hay dos cubos que separar.
     */
    public static function discoPublico(): string
    {
        return config('lotea.discos.publico') ?: static::nombreDelDisco();
    }

    /** El de los documentos y las fotos de subasta, que salen firmados. */
    public static function discoPrivado(): string
    {
        return config('lotea.discos.privado') ?: static::nombreDelDisco();
    }

    /** ¿Los archivos están en un disco local que el navegador puede pedir directo? */
    public static function esLocalPublico(): bool
    {
        return config('filesystems.disks.'.static::nombreDelDisco().'.driver') === 'local'
            && filled(config('filesystems.disks.'.static::nombreDelDisco().'.url'));
    }

    /**
     * La ruta del archivo dentro del disco, con su conversión si se pide.
     *
     * Es la misma que usa medialibrary para guardar, así que vale para leer de
     * cualquier disco: local, FTP o lo que venga después.
     */
    public static function rutaDe(Media $media, ?string $conversion = null): string
    {
        return $media->getPathRelativeToRoot($conversion ?? '');
    }

    /**
     * El contenido del archivo, de la copia local si está o del FTP si no.
     *
     * Devuelve la ruta del archivo local listo para servir, para poder mandarlo
     * con sendFile y que el servidor web haga el trabajo pesado.
     */
    public static function archivoLocal(Media $media, ?string $conversion = null): string
    {
        return static::archivoLocalDeRuta(static::rutaDe($media, $conversion));
    }

    /** Igual, para archivos que no pasan por medialibrary: los logos. */
    public static function archivoLocalDeRuta(string $ruta): string
    {
        $cache = Storage::disk(static::DISCO_CACHE);

        if (! $cache->exists($ruta)) {
            static::traerDelOrigen($ruta, $cache);
        }

        return $cache->path($ruta);
    }

    protected static function traerDelOrigen(string $ruta, Filesystem $cache): void
    {
        $origen = static::disco();

        if (! $origen->exists($ruta)) {
            throw new RuntimeException("El archivo «{$ruta}» no está en el disco ".static::nombreDelDisco().'.');
        }

        $flujo = $origen->readStream($ruta);

        if ($flujo === false || $flujo === null) {
            throw new RuntimeException("No se pudo leer «{$ruta}» del disco ".static::nombreDelDisco().'.');
        }

        // writeStream y no put: un archivo de 4 MB no tiene por qué pasar
        // entero por la memoria de PHP.
        $cache->writeStream($ruta, $flujo);

        if (is_resource($flujo)) {
            fclose($flujo);
        }
    }

    /** Borra la copia local de un archivo; la siguiente lectura la rehace. */
    public static function olvidarCache(Media $media, ?string $conversion = null): void
    {
        Storage::disk(static::DISCO_CACHE)->delete(static::rutaDe($media, $conversion));
    }

    /**
     * Borra la copia local de un archivo y de todas sus conversiones.
     *
     * Se llama al borrar el medium: si no, el disco se llena de fotos de
     * carros que ya no existen.
     */
    public static function olvidarTodoDe(Media $media): void
    {
        $carpeta = app(config('media-library.path_generator'))->getPath($media);

        Storage::disk(static::DISCO_CACHE)->deleteDirectory(rtrim($carpeta, '/'));
    }
}
