<?php

namespace App\Support;

use App\Models\Unidad;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Dónde se guarda cada archivo dentro del disco.
 *
 * Medialibrary por defecto guarda en carpetas numéricas —`42/foto.jpg`— que en
 * un FTP compartido con otros sistemas no le dicen nada a nadie. Aquí se
 * ordenan por concesionario y unidad, así:
 *
 *     autos-del-valle/unidades/12/fotos/37/frente.jpg
 *     autos-del-valle/unidades/12/fotos/37/conversions/frente-web.webp
 *     autos-del-valle/documentos/12/40/titulo.pdf
 *
 * Se puede abrir el FTP con cualquier cliente y entender qué hay, y si un
 * cliente se da de baja su carpeta se borra entera.
 *
 * Los identificadores son numéricos a propósito: el stock del carro se puede
 * editar y las rutas ya guardadas quedarían apuntando a la nada. El id del
 * medium al final es lo que garantiza que dos archivos con el mismo nombre en
 * la misma unidad no se pisen.
 */
class RutaDeArchivos implements PathGenerator
{
    /** @var array<int, string> El slug de cada unidad ya consultada. */
    protected static array $empresas = [];

    /** Los tests crean unidades nuevas con ids repetidos entre casos. */
    public static function olvidar(): void
    {
        static::$empresas = [];
    }

    public function getPath(Media $media): string
    {
        return $this->carpetaDe($media);
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->carpetaDe($media).'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->carpetaDe($media).'responsive/';
    }

    protected function carpetaDe(Media $media): string
    {
        $empresa = $this->empresaDe($media);
        $coleccion = str($media->collection_name)->slug()->value() ?: 'archivos';

        if ($media->model_type !== Unidad::class) {
            return "{$empresa}/{$coleccion}/{$media->getKey()}/";
        }

        return "{$empresa}/unidades/{$media->model_id}/{$coleccion}/{$media->getKey()}/";
    }

    /**
     * El concesionario dueño del archivo.
     *
     * Se lee sin el filtro de empresa activa: al generar la ruta de una
     * conversión puede no haber contexto, y el archivo es de quien es aunque
     * nadie tenga sesión.
     *
     * Se recuerda por unidad dentro del request: subir treinta fotos de un
     * carro preguntaría treinta veces por el mismo concesionario.
     */
    protected function empresaDe(Media $media): string
    {
        if ($media->model_type !== Unidad::class) {
            return 'sin-empresa';
        }

        $id = (int) $media->model_id;

        return static::$empresas[$id] ??= Tenancy::sinFiltro(
            fn () => Unidad::withTrashed()->with('empresa')->find($id)?->empresa?->slug
        ) ?: 'sin-empresa';
    }
}
