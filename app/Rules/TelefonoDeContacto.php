<?php

namespace App\Rules;

use App\Support\WhatsApp;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Un teléfono al que de verdad se puede llamar.
 *
 * Antes bastaba con ocho caracteres cualesquiera, así que «aaaaaaaa» entraba
 * como número y el vendedor se enteraba al intentar llamar. Un prospecto sin
 * teléfono no es un prospecto.
 *
 * Acepta las tres formas en que la gente lo escribe —«5555-1234», «+502 5555
 * 1234», «50255551234»— porque nadie va a escribirlo como al sistema le
 * convenga.
 *
 * Y acepta números de fuera a propósito: en este negocio buena parte de los
 * compradores llaman desde Estados Unidos para comprarle el carro a su familia
 * aquí. Rechazarlos por no tener ocho dígitos sería perder ventas.
 */
class TelefonoDeContacto implements ValidationRule
{
    /** Los primeros dígitos que existen en Guatemala: 2 fijo, 3-5 móvil, 6-7 interior. */
    protected const PRIMEROS_VALIDOS = '234567';

    protected const DIGITOS_LOCALES = 8;

    /** Lo más corto y lo más largo que puede tener un número del mundo. */
    protected const MINIMO_INTERNACIONAL = 10;

    protected const MAXIMO_INTERNACIONAL = 15;

    public function validate(string $atributo, mixed $valor, Closure $fallar): void
    {
        $digitos = preg_replace('/\D/', '', (string) $valor) ?? '';

        if ($digitos === '') {
            $fallar('Escriba un número de teléfono.');

            return;
        }

        // Con el código de Guatemala delante, se mira el número de atrás.
        if (str_starts_with($digitos, WhatsApp::CODIGO_GUATEMALA)
            && strlen($digitos) === strlen(WhatsApp::CODIGO_GUATEMALA) + self::DIGITOS_LOCALES) {
            $digitos = substr($digitos, strlen(WhatsApp::CODIGO_GUATEMALA));
        }

        if (strlen($digitos) === self::DIGITOS_LOCALES) {
            if (! str_contains(self::PRIMEROS_VALIDOS, $digitos[0])) {
                $fallar('Ese número no parece de Guatemala. Revíselo, por favor.');
            }

            return;
        }

        // Un número de fuera: no se le exige forma, solo que sea verosímil.
        if (strlen($digitos) >= self::MINIMO_INTERNACIONAL
            && strlen($digitos) <= self::MAXIMO_INTERNACIONAL) {
            return;
        }

        $fallar(strlen($digitos) < self::DIGITOS_LOCALES
            ? 'Al número le faltan dígitos. En Guatemala son ocho.'
            : 'Ese número tiene más dígitos de los que existen.');
    }
}
