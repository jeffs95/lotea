<?php

namespace App\Filament\Resources\Unidades\Actions;

use App\Models\Unidad;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Rebajar el precio de un carro sin entrar a su ficha.
 *
 * Esto se usa mirando el patio, no sentado: «este lleva tres semanas, bajale
 * cinco mil». Obligar a abrir la ficha, buscar la sección y guardar todo el
 * formulario para cambiar un número es lo que hace que la gente no lo use y el
 * carro siga ahí parado otro mes.
 */
class PonerEnOfertaAction
{
    public static function make(): Action
    {
        return Action::make('oferta')
            ->label(fn (Unidad $record) => $record->tieneOferta() ? 'Cambiar la oferta' : 'Poner en oferta')
            ->icon(Heroicon::OutlinedTag)
            ->color('danger')
            ->modalHeading(fn (Unidad $record) => 'Oferta · '.$record->descripcion)
            ->modalSubmitActionLabel('Guardar la oferta')
            ->fillForm(fn (Unidad $record) => [
                'precio_oferta' => $record->precio_oferta,
                'oferta_hasta' => $record->oferta_hasta,
                'oferta_etiqueta' => $record->oferta_etiqueta,
            ])
            ->schema(fn (Unidad $record) => [
                Placeholder::make('actual')
                    ->label('Precio de lista')
                    ->content(new HtmlString(
                        '<span class="text-lg font-semibold">Q '
                        .number_format((float) $record->precio_lista, 0).'</span>'
                        .($record->precio_minimo
                            ? '<span class="ml-3 text-sm text-gray-500">mínimo autorizado: Q '
                                .number_format((float) $record->precio_minimo, 0).'</span>'
                            : '')
                    )),

                TextInput::make('precio_oferta')
                    ->label('Nuevo precio')
                    ->numeric()
                    ->prefix('Q')
                    ->required()
                    ->lt('precio_lista_oculto')
                    ->rules([
                        function (string $atributo, $valor, \Closure $fallar) use ($record) {
                            if ((float) $valor >= (float) $record->precio_lista) {
                                $fallar('Tiene que ser menor que el precio de lista.');
                            }
                        },
                    ])
                    ->helperText('El de lista no se toca: el portal enseña los dos, el viejo tachado.'),

                DatePicker::make('oferta_hasta')
                    ->label('Vigente hasta')
                    ->native(false)
                    ->minDate(today())
                    ->helperText('Opcional. Vacío: dura hasta que la quite.'),

                TextInput::make('oferta_etiqueta')
                    ->label('Texto del distintivo')
                    ->maxLength(40)
                    ->placeholder('Oferta')
                    ->helperText('Lo que se lee en rojo sobre la foto. Por ejemplo «Candente».'),
            ])
            ->action(function (Unidad $record, array $data) {
                $record->update([
                    'precio_oferta' => $data['precio_oferta'],
                    'oferta_hasta' => $data['oferta_hasta'] ?? null,
                    'oferta_etiqueta' => $data['oferta_etiqueta'] ?? null,
                ]);

                $record->refresh();

                $aviso = Notification::make()
                    ->title('Oferta puesta')
                    ->body("Se anuncia Q {$record->ahorro} menos, un {$record->descuento_porcentaje}% de descuento.");

                // Se permite bajar del piso —liquidar a veces es lo correcto—
                // pero quien lo hizo tiene que verlo, y queda en el rastro.
                if ($record->ofertaBajaDelMinimo()) {
                    $aviso->title('Oferta puesta, por debajo del mínimo')
                        ->body('Queda Q '.number_format((float) $record->precio_minimo - (float) $record->precio_oferta, 0)
                            .' bajo el precio mínimo autorizado. Se guardó igual.')
                        ->warning()
                        ->persistent();
                } else {
                    $aviso->success();
                }

                $aviso->send();
            });
    }

    /** Quitar la rebaja y volver al precio de lista. */
    public static function quitar(): Action
    {
        return Action::make('quitar_oferta')
            ->label('Quitar la oferta')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('gray')
            ->visible(fn (Unidad $record) => $record->tieneOferta())
            ->requiresConfirmation()
            ->modalDescription('El carro vuelve a su precio de lista y deja de salir entre las ofertas.')
            ->action(function (Unidad $record) {
                $record->update([
                    'precio_oferta' => null,
                    'oferta_hasta' => null,
                    'oferta_etiqueta' => null,
                ]);

                Notification::make()->title('Oferta quitada')->success()->send();
            });
    }
}
