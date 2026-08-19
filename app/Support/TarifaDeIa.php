<?php

namespace App\Support;

/**
 * Lo que cuesta usar el modelo, para poder ponerle precio al add-on.
 *
 * Los precios los publica OpenRouter por millón de tokens y cambian con el
 * tiempo; viven acá y en el .env para poder actualizarlos sin buscar por todo
 * el código.
 */
class TarifaDeIa
{
    public static function porMillonEntrada(): float
    {
        return (float) config('services.openrouter.precio_entrada', 0.25);
    }

    public static function porMillonSalida(): float
    {
        return (float) config('services.openrouter.precio_salida', 0.75);
    }

    /** A ocho decimales: una lectura cuesta diezmilésimas de dólar. */
    public static function costo(int $tokensEntrada, int $tokensSalida): float
    {
        return round(
            ($tokensEntrada / 1_000_000) * self::porMillonEntrada()
            + ($tokensSalida / 1_000_000) * self::porMillonSalida(),
            8,
        );
    }

    /** Lo mismo en quetzales, que es la moneda en la que se cobra. */
    public static function enQuetzales(float $usd, ?float $tipoCambio = null): float
    {
        return round($usd * ($tipoCambio ?? (float) config('services.openrouter.tipo_cambio', 7.70)), 4);
    }
}
