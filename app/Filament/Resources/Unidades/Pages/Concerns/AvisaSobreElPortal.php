<?php

namespace App\Filament\Resources\Unidades\Pages\Concerns;

use App\Support\RequisitosDelPortal;
use Filament\Notifications\Notification;

/**
 * Explica por qué la unidad que se acaba de guardar no va a salir en el portal.
 *
 * El modelo apaga «Publicado» solo cuando falta el precio o una foto. Es la
 * decisión correcta —un carro sin precio en la vitrina hace quedar mal al
 * concesionario— pero pasaba en silencio: el usuario marcaba el interruptor,
 * guardaba, y la unidad no aparecía sin que nadie le dijera qué faltó.
 */
trait AvisaSobreElPortal
{
    protected function avisarSobreElPortal(): void
    {
        $pidioPublicar = (bool) ($this->data['publicado'] ?? false);

        if (! $pidioPublicar) {
            return;
        }

        $unidad = $this->record->refresh();

        // El modelo lo apagó: faltaba algo sin lo que no se publica.
        if (! $unidad->publicado) {
            $faltan = RequisitosDelPortal::trabas($unidad->precio_lista, $unidad->tieneAlgunaFoto());

            Notification::make()
                ->title('Se guardó, pero no se publicó')
                ->body('Para que aparezca en el portal falta '.collect($faltan)->join(', ', ' y ')
                    .'. La unidad ya está en el inventario: agregue eso y vuelva a marcar «Publicado».')
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        // Publicada de verdad, pero el estado todavía no la deja a la venta.
        if (! RequisitosDelPortal::elEstadoAdmiteVenta($unidad->estado)) {
            Notification::make()
                ->title('Quedó publicada, pero todavía no se ve')
                ->body('En «'.$unidad->estado->getLabel().'» el carro no se ofrece como disponible. '
                    .'Va a aparecer solo, sin tocar nada, cuando lo pase a '
                    .RequisitosDelPortal::estadosQueSeVen().'.')
                ->info()
                ->persistent()
                ->send();
        }
    }
}
