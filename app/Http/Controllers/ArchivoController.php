<?php

namespace App\Http\Controllers;

use App\Models\Unidad;
use App\Support\AlmacenDeArchivos;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Sirve las fotos y los documentos que viven en el FTP.
 *
 * Un disco FTP no tiene URL pública, así que los archivos pasan por aquí. Eso
 * de paso arregla algo que estaba mal: con el disco público, el título de un
 * carro o su tarjeta de circulación quedaban accesibles a quien diera con la
 * URL. Ahora cada archivo se autoriza antes de entregarse.
 */
class ArchivoController extends Controller
{
    /** Colecciones que el portal muestra a cualquiera. */
    protected const PUBLICAS = ['fotos'];

    public function __invoke(Request $request, Media $media, ?string $conversion = null): Response
    {
        // Sin filtro de empresa: quién puede ver el archivo se decide abajo, y
        // el portal público no tiene empresa activa.
        $unidad = Tenancy::sinFiltro(fn () => Unidad::withTrashed()->find($media->model_id));

        abort_if($media->model_type !== Unidad::class || ! $unidad, 404);
        abort_unless($this->puedeVer($unidad, $media), 403);

        try {
            return $this->entregar($media, $conversion, $unidad);
        } catch (Throwable $e) {
            // El archivo está registrado en la base pero no en el disco: pasa
            // cuando alguien limpia el FTP a mano. No es un 500 nuestro.
            report($e);

            abort(404);
        }
    }

    /**
     * Las fotos de una unidad publicada las ve cualquiera: son el catálogo.
     *
     * Todo lo demás —las fotos de subasta, que son la prueba de cómo venía el
     * carro, los documentos y las fotos de lo que aún no está a la venta— es
     * del concesionario y solo lo ve su gente.
     */
    /**
     * Un enlace temporal al archivo, si el almacenamiento los admite.
     *
     * Solo R2 y compañía; con un FTP o un disco local no hay nada que firmar y
     * el archivo se sigue entregando desde aquí.
     */
    protected function enlaceFirmado(Media $media, ?string $conversion): ?string
    {
        // Por el driver y no por la interfaz: en Laravel hasta el disco local
        // implementa Cloud, así que preguntarle a la clase decía que sí a todo,
        // incluidos los discos fingidos de los tests.
        if (config("filesystems.disks.{$media->disk}.driver") !== 's3') {
            return null;
        }

        try {
            $disco = Storage::disk($media->disk);

            return $disco->temporaryUrl(
                AlmacenDeArchivos::rutaDe($media, $conversion),
                now()->addMinutes((int) config('lotea.minutos_de_enlace_firmado', 15)),
            );
        } catch (Throwable) {
            // Un disco en la nube que no sabe firmar: se entrega como siempre.
            return null;
        }
    }

    protected function puedeVer(Unidad $unidad, Media $media): bool
    {
        if ($unidad->publicado && in_array($media->collection_name, self::PUBLICAS, true)) {
            return true;
        }

        $usuario = Auth::user();

        return $usuario !== null
            && $usuario->empresas()->whereKey($unidad->empresa_id)->exists();
    }

    protected function entregar(Media $media, ?string $conversion, Unidad $unidad): Response
    {
        /*
         * Si el almacenamiento sabe firmar enlaces, que entregue él el archivo.
         *
         * La autorización ya se resolvió arriba; lo único que queda es mover
         * bytes, y eso no tiene por qué hacerlo PHP. Se responde con un enlace
         * firmado que caduca en minutos: sirve para abrir el documento, no para
         * reenviarlo por WhatsApp y que siga funcionando mañana.
         */
        if ($enlace = $this->enlaceFirmado($media, $conversion)) {
            return redirect()->away($enlace);
        }

        $archivo = AlmacenDeArchivos::archivoLocal($media, $conversion);

        $respuesta = response()->file($archivo, [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
        ]);

        // Una foto del catálogo no cambia nunca: si cambia, es otro archivo con
        // otro id. Cachearla un año le quita al servidor el trabajo de volver a
        // entregarla en cada visita.
        return $unidad->publicado && in_array($media->collection_name, self::PUBLICAS, true)
            ? $respuesta->setMaxAge(31536000)->setPublic()
            : $respuesta->setPrivate()->setMaxAge(0);
    }
}
