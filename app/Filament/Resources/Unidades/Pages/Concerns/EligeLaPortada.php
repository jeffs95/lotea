<?php

namespace App\Filament\Resources\Unidades\Pages\Concerns;

use App\Models\Unidad;
use Filament\Notifications\Notification;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Elegir con qué foto se presenta el carro.
 *
 * La portada es la primera foto, y hasta ahora la única forma de cambiarla era
 * arrastrarla hasta el principio. Eso funciona con tres fotos y se vuelve
 * incómodo con quince, sobre todo en un teléfono: hay que arrastrar sobre una
 * cuadrícula que se desplaza sola.
 *
 * Y la portada no es un detalle: es lo único que ve quien recorre el catálogo.
 * Si la primera que se subió es la del tablero sucio, ese carro no lo abre
 * nadie por buenas que sean las otras catorce.
 */
trait EligeLaPortada
{
    public function hacerPortada(int $mediaId): void
    {
        $unidad = $this->getRecord();

        if (! $unidad instanceof Unidad) {
            return;
        }

        $fotos = $unidad->getMedia('fotos');
        $elegida = $fotos->firstWhere('id', $mediaId);

        if (! $elegida) {
            return;
        }

        /*
         * Medialibrary ordena por una columna propia, así que «hacer portada»
         * es ponerla primera en ese orden. Se mandan todos los ids y no solo el
         * elegido: setNewOrder numera de uno en adelante lo que recibe, y si se
         * le pasa uno suelto deja al resto con su orden viejo y el resultado
         * queda mezclado.
         */
        $orden = $fotos->pluck('id')
            ->reject(fn (int $id) => $id === $mediaId)
            ->prepend($mediaId)
            ->all();

        Media::setNewOrder($orden);

        $unidad->refresh();

        Notification::make()
            ->title('Portada cambiada')
            ->body('Es la primera que va a ver quien entre al portal.')
            ->success()
            ->send();
    }
}
