<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login;

/**
 * La pantalla de entrada, en sus dos puertas.
 *
 * Filament trae una tarjeta centrada que sirve para cualquier cosa y no dice
 * nada de nadie. Es la primera pantalla que ve un cliente al que le acabamos de
 * cobrar una licencia, así que vale la pena que se sienta un producto y no un
 * formulario suelto.
 *
 * La mitad de la izquierda es de marca; la de la derecha, el formulario de
 * siempre. Cada panel pone su propio texto y su propio color: quien entra tiene
 * que saber de un vistazo si está en la puerta del concesionario o en la de
 * Lotea, que es justo la confusión que se daba con las dos rutas parecidas.
 */
abstract class Acceso extends Login
{
    protected static string $layout = 'filament.auth.acceso';

    /** La marca la pinta la portada; el logo de Filament estorbaría. */
    public function hasLogo(): bool
    {
        return false;
    }

    /**
     * Lo que se muestra en la mitad de marca.
     *
     * @return array{
     *     titulo: string,
     *     lema: string,
     *     puntos: array<int, string>,
     *     color: string,
     *     etiqueta: string,
     *     nota: ?string,
     * }
     */
    abstract public function portada(): array;

    /** @return array<string, mixed> */
    protected function getLayoutData(): array
    {
        return [
            ...parent::getLayoutData(),
            'portada' => $this->portada(),
        ];
    }
}
