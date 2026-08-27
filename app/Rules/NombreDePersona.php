<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Un nombre que parece un nombre.
 *
 * Antes se pedía solo que fuera texto, así que «123456», «...» o «asdasd»
 * entraban igual. No se trata de adivinar si alguien se llama como dice —eso no
 * se puede— sino de que el vendedor no llame a una ficha que dice «xxxx».
 *
 * Lo que se exige es mínimo: que haya letras de verdad y no una sola.
 */
class NombreDePersona implements ValidationRule
{
    protected const LETRAS_MINIMAS = 3;

    public function validate(string $atributo, mixed $valor, Closure $fallar): void
    {
        $texto = trim((string) $valor);

        // Se cuentan las letras, no los caracteres: «J. R.» tiene cuatro
        // caracteres visibles y solo dos letras.
        $letras = preg_match_all('/\p{L}/u', $texto);

        if ($letras < self::LETRAS_MINIMAS) {
            $fallar('Escriba su nombre completo.');

            return;
        }

        // La misma letra repetida no es un nombre: «aaaa», «xxxxx».
        if (preg_match('/^(\p{L})\1+$/u', preg_replace('/\s+/u', '', $texto) ?? '')) {
            $fallar('Escriba su nombre completo.');
        }
    }
}
