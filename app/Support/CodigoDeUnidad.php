<?php

namespace App\Support;

use App\Models\Unidad;

/**
 * El código corto que identifica a un carro en la calle.
 *
 * Sin vocales (para no formar palabras por accidente) y sin los caracteres que
 * se confunden al dictarlo: O contra 0, I contra 1, S contra 5. Alguien va a
 * tener que leerlo por teléfono alguna vez.
 */
class CodigoDeUnidad
{
    public const ALFABETO = '2346789BCDFGHJKLMNPQRTVWXYZ';

    public const LARGO = 6;

    public static function generar(): string
    {
        do {
            $codigo = self::aleatorio();
        } while (self::yaExiste($codigo));

        return $codigo;
    }

    protected static function aleatorio(): string
    {
        $codigo = '';

        for ($i = 0; $i < self::LARGO; $i++) {
            $codigo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }

        return $codigo;
    }

    protected static function yaExiste(string $codigo): bool
    {
        return Tenancy::sinFiltro(
            fn () => Unidad::withTrashed()->where('codigo_qr', $codigo)->exists()
        );
    }

    /** Acepta el código escrito a mano, con minúsculas o espacios de más. */
    public static function normalizar(string $codigo): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo));
    }
}
