<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Saca la latitud y la longitud del enlace que la gente copia de Google Maps.
 *
 * Nadie se sabe las coordenadas de su patio, pero cualquiera sabe abrir Maps,
 * buscar su local y darle a «Compartir». Pedir el enlace y sacarlas nosotros es
 * la diferencia entre que el cliente llene esto o lo deje vacío.
 *
 * Los enlaces vienen en varias formas según de dónde se copien:
 *
 *     .../@14.6349,-90.5069,17z/...        el centro del mapa
 *     .../place/...!3d14.6349!4d-90.5069   el punto exacto del lugar
 *     ...?q=14.6349,-90.5069               una búsqueda por coordenada
 *     14.6349, -90.5069                    pegadas a mano
 */
class Coordenadas
{
    /** Guatemala está entre estos valores; sirve para descartar disparates. */
    protected const LATITUD_MAXIMA = 90;

    protected const LONGITUD_MAXIMA = 180;

    /**
     * @return array{latitud: float, longitud: float}|null
     */
    public static function desde(?string $texto): ?array
    {
        if (blank($texto)) {
            return null;
        }

        $patrones = [
            // El punto exacto del lugar: es el más fiable cuando está.
            '/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/',
            '/@(-?\d+\.\d+),\s*(-?\d+\.\d+)/',
            '/[?&](?:q|query|ll|destination)=(-?\d+\.\d+),\s*(-?\d+\.\d+)/',
            '/^\s*(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)\s*$/',
        ];

        foreach ($patrones as $patron) {
            if (preg_match($patron, $texto, $coincide)) {
                return static::validar((float) $coincide[1], (float) $coincide[2]);
            }
        }

        return static::desdeEnlaceCorto($texto);
    }

    /**
     * Los `maps.app.goo.gl` que da el botón «Compartir» del celular.
     *
     * Es el caso más común de todos y no trae las coordenadas dentro: hay que
     * seguir la redirección para llegar al enlace largo. Si Google no responde
     * se devuelve null y el usuario podrá escribirlas a mano.
     */
    protected static function desdeEnlaceCorto(string $texto): ?array
    {
        if (! preg_match('#^https?://(maps\.app\.goo\.gl|goo\.gl/maps)/\S+$#i', trim($texto))) {
            return null;
        }

        try {
            $destino = Http::timeout(5)
                ->withoutRedirecting()
                ->get(trim($texto))
                ->header('Location');
        } catch (Throwable) {
            return null;
        }

        // Sin recursión infinita: el enlace largo ya no es uno corto.
        return blank($destino) ? null : static::desde($destino);
    }

    /**
     * @return array{latitud: float, longitud: float}|null
     */
    protected static function validar(float $latitud, float $longitud): ?array
    {
        if (abs($latitud) > self::LATITUD_MAXIMA || abs($longitud) > self::LONGITUD_MAXIMA) {
            return null;
        }

        // 0,0 es un punto en el Atlántico: casi siempre significa «no hay dato».
        if ($latitud === 0.0 && $longitud === 0.0) {
            return null;
        }

        return ['latitud' => $latitud, 'longitud' => $longitud];
    }

    /** El enlace para abrir el punto en Google Maps. */
    public static function google(float $latitud, float $longitud): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.$latitud.','.$longitud;
    }

    /** El de Waze, que es con el que casi todo el mundo maneja aquí. */
    public static function waze(float $latitud, float $longitud): string
    {
        return 'https://waze.com/ul?ll='.$latitud.','.$longitud.'&navigate=yes';
    }
}
