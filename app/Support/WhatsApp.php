<?php

namespace App\Support;

/**
 * Arma los enlaces de WhatsApp, que aquí es por donde se vende.
 *
 * El código de país estaba escrito a mano en tres vistas, siempre anteponiendo
 * 502. Si el cliente guardaba su número ya con el código —«502 5555 1234», que
 * es como lo escribe medio mundo— salía «5025025551234» y el enlace no abría a
 * nadie. Aquí se decide una sola vez y bien.
 */
class WhatsApp
{
    public const CODIGO_GUATEMALA = '502';

    /** Cuántos dígitos tiene un número guatemalteco sin código de país. */
    protected const DIGITOS_LOCALES = 8;

    /** Solo dígitos y con código de país, que es lo que espera wa.me. */
    public static function internacional(?string $numero): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $numero);

        if (blank($digitos)) {
            return null;
        }

        // Un número local de aquí: le falta el código.
        if (strlen($digitos) === self::DIGITOS_LOCALES) {
            return self::CODIGO_GUATEMALA.$digitos;
        }

        // Cualquier otra cosa ya trae su código, sea de Guatemala o de fuera.
        return $digitos;
    }

    /** El enlace listo para poner en un href, con mensaje opcional. */
    public static function enlace(?string $numero, ?string $mensaje = null): ?string
    {
        $destino = self::internacional($numero);

        if ($destino === null) {
            return null;
        }

        return 'https://wa.me/'.$destino
            .(filled($mensaje) ? '?text='.rawurlencode($mensaje) : '');
    }
}
