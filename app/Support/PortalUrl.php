<?php

namespace App\Support;

use App\Models\Empresa;

/**
 * Genera enlaces del portal sin que el resto del código tenga que saber si el
 * cliente ya tiene dominio propio o si estamos en desarrollo.
 */
class PortalUrl
{
    public static function ruta(string $nombre, Empresa $empresa, array $parametros = []): string
    {
        if (filled($empresa->dominio)) {
            return route("portal.{$nombre}", $parametros).'';
        }

        return route("portal.demo.{$nombre}", ['empresaSlug' => $empresa->slug, ...$parametros]);
    }

    public static function inicio(Empresa $empresa): string
    {
        return self::ruta('inicio', $empresa);
    }

    public static function catalogo(Empresa $empresa, array $filtros = []): string
    {
        return self::ruta('catalogo', $empresa, $filtros);
    }

    public static function unidad(Empresa $empresa, string $slug): string
    {
        return self::ruta('unidad', $empresa, ['slug' => $slug]);
    }

    /** Dónde encontrarlos y cómo escribirles. */
    public static function contacto(Empresa $empresa): string
    {
        return self::ruta('contacto', $empresa);
    }
}
