<?php

namespace App\Support;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Dónde se guarda cada archivo dentro del disco.
 *
 * Medialibrary por defecto guarda en carpetas numéricas —`42/foto.jpg`— que en
 * un disco compartido con otros sistemas no le dicen nada a nadie. Aquí se
 * ordenan por concesionario y por el registro dueño del archivo, así:
 *
 *     autos-del-valle/unidades/12/fotos/37/frente.jpg
 *     autos-del-valle/unidades/12/fotos/37/conversions/frente-web.webp
 *     autos-del-valle/clientes/8/identificacion/40/dpi-frente.jpg
 *
 * Se puede abrir el disco con cualquier cliente y entender qué hay, y si un
 * concesionario se da de baja su carpeta se borra entera. Esto último es la
 * razón de que el concesionario vaya primero y no sea decorativo: con los
 * documentos de identidad de los compradores adentro, «borrar lo de este
 * cliente» tiene que ser una operación que se pueda hacer sin ir a mano.
 *
 * Los identificadores son numéricos a propósito: el stock del carro se puede
 * editar y las rutas ya guardadas quedarían apuntando a la nada. El id del
 * medium al final es lo que garantiza que dos archivos con el mismo nombre en
 * el mismo registro no se pisen.
 */
class RutaDeArchivos implements PathGenerator
{
    /** @var array<string, string> El slug del concesionario de cada registro ya consultado. */
    protected static array $empresas = [];

    /** Los tests crean registros nuevos con ids repetidos entre casos. */
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
        $grupo = $this->grupoDe($media);
        $coleccion = str($media->collection_name)->slug()->value() ?: 'archivos';

        if ($grupo === '') {
            return "{$empresa}/{$coleccion}/{$media->getKey()}/";
        }

        return "{$empresa}/{$grupo}/{$media->model_id}/{$coleccion}/{$media->getKey()}/";
    }

    /**
     * La carpeta por tipo de registro: `unidades`, `clientes`.
     *
     * Sale de la tabla del modelo y no de una lista escrita a mano para que un
     * modelo nuevo con archivos quede ordenado solo. Se pregunta a una
     * instancia vacía, sin tocar la base: así un archivo cuyo registro ya se
     * borró del todo conserva la ruta con la que se guardó y se puede seguir
     * borrando del disco.
     */
    protected function grupoDe(Media $media): string
    {
        $clase = $media->model_type;

        if (! is_string($clase) || ! is_subclass_of($clase, Model::class)) {
            return '';
        }

        return (new $clase)->getTable();
    }

    /**
     * El concesionario dueño del archivo.
     *
     * Se lee sin el filtro de empresa activa: al generar la ruta de una
     * conversión puede no haber contexto, y el archivo es de quien es aunque
     * nadie tenga sesión.
     *
     * Se recuerda por registro dentro del request: subir treinta fotos de un
     * carro preguntaría treinta veces por el mismo concesionario.
     */
    protected function empresaDe(Media $media): string
    {
        $clave = $media->model_type.':'.$media->model_id;

        return static::$empresas[$clave] ??= Tenancy::sinFiltro(
            fn () => $this->slugDe($this->duenoDe($media))
        ) ?: 'sin-empresa';
    }

    /** El registro al que pertenece el archivo, aunque esté en la papelera. */
    protected function duenoDe(Media $media): ?Model
    {
        $clase = $media->model_type;

        if (! is_string($clase) || ! is_subclass_of($clase, Model::class)) {
            return null;
        }

        $consulta = $clase::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($clase), true)) {
            $consulta->withTrashed();
        }

        return $consulta->find($media->model_id);
    }

    protected function slugDe(?Model $modelo): ?string
    {
        if ($modelo instanceof Empresa) {
            return $modelo->slug;
        }

        return $modelo && method_exists($modelo, 'empresa')
            ? $modelo->empresa?->slug
            : null;
    }
}
