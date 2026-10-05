<?php

namespace App\Support;

use App\Models\Empresa;
use App\Models\Unidad;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Las fotos que se turnan detrás del titular del portal.
 *
 * Primero la portada que subió el concesionario, y después los carros que
 * tiene publicados. Así el fondo no es una foto de archivo que se queda igual
 * tres años: es el inventario de hoy, y se renueva solo cada vez que entra o
 * sale una unidad, sin que nadie tenga que acordarse de cambiarlo.
 *
 * Las fotos salen de las unidades que la página ya trajo para mostrar abajo,
 * así que esto no pregunta nada más a la base.
 */
class FondoDelPortal
{
    /**
     * Cuántas se turnan.
     *
     * Son el peso de la portada: cada una es una imagen grande que el
     * navegador acaba bajando. Cinco alcanzan para que no se note el ciclo y
     * no castigan a quien entra desde el teléfono con datos.
     */
    public const CUANTAS = 5;

    /** Segundos que se queda cada una. */
    public const SEGUNDOS = 5;

    /**
     * @param  Collection<int, Unidad>  $unidades  las que la página ya cargó
     * @return Collection<int, string>
     */
    public static function imagenes(?Empresa $empresa, Collection $unidades, int $cuantas = self::CUANTAS): Collection
    {
        return collect([$empresa?->portada_url])
            ->concat($unidades->map(static fn (Unidad $unidad) => static::fotoDe($unidad)))
            ->filter()
            ->unique()
            ->take(max(1, $cuantas))
            ->values();
    }

    /**
     * La foto de portada de una unidad, en el tamaño de pantalla.
     *
     * Se pregunta si la conversión está hecha en vez de pedirla y confiar:
     * las conversiones se generan en segundo plano, y la URL de una que
     * todavía no existe se ve como una imagen rota a pantalla completa.
     */
    protected static function fotoDe(Unidad $unidad): ?string
    {
        $foto = $unidad->getFirstMedia('fotos');

        if (! $foto instanceof Media) {
            return null;
        }

        return $foto->hasGeneratedConversion('web')
            ? $foto->getUrl('web')
            : $foto->getUrl();
    }
}
