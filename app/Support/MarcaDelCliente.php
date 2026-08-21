<?php

namespace App\Support;

use App\Models\Empresa;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Model;

/**
 * La marca del concesionario que está viendo el panel ahora mismo.
 *
 * No se puede preguntar a Filament::getTenant(): el panel arranca en el
 * middleware SetUpPanel, que corre antes de que la tenancy resuelva la
 * empresa. Cuando el panel pide los colores, ese tenant todavía no existe.
 * Así que la empresa se saca del propio request, que sí está disponible desde
 * el primer momento.
 *
 * Los datos de marca los da el modelo: aquí solo se resuelve de quién son.
 */
class MarcaDelCliente
{
    public const COLOR_POR_DEFECTO = Empresa::COLOR_POR_DEFECTO;

    protected static ?Empresa $empresa = null;

    /**
     * Con qué slug se resolvió lo que está en memoria.
     *
     * Se compara en cada llamada en vez de usar una bandera de «ya resuelto»:
     * si el proceso atiende dos requests seguidos (Octane, tests), el segundo
     * cliente vería la marca del primero. Es el mismo tipo de fuga que la de
     * los roles, y aquí se corta de raíz.
     */
    protected static ?string $slugEnMemoria = null;

    public static function empresa(): ?Empresa
    {
        $slug = static::slugDelRequest();

        if (static::$slugEnMemoria !== $slug) {
            static::$slugEnMemoria = $slug;
            static::$empresa = $slug ? Empresa::firstWhere('slug', $slug) : null;
        }

        return static::$empresa;
    }

    public static function olvidar(): void
    {
        static::$empresa = null;
        static::$slugEnMemoria = null;
    }

    public static function nombre(): string
    {
        return static::empresa()?->getFilamentName() ?? 'Lotea';
    }

    public static function color(): string
    {
        return static::empresa()?->color_de_marca ?? static::COLOR_POR_DEFECTO;
    }

    /** @return array<int, string> */
    public static function paleta(): array
    {
        return Color::hex(static::color());
    }

    public static function logo(): ?string
    {
        return static::empresa()?->logo_url;
    }

    /** El logo del cliente sobre el fondo oscuro del panel. */
    public static function logoOscuro(): ?string
    {
        return static::empresa()?->logo_oscuro_url;
    }

    public static function favicon(): ?string
    {
        return static::empresa()?->favicon_url;
    }

    /** El slug del concesionario en la URL, o null si no estamos en su panel. */
    protected static function slugDelRequest(): ?string
    {
        $tenant = request()?->route()?->parameter('tenant');

        // Según en qué punto de su arranque pregunte el panel, el parámetro
        // puede venir ya resuelto como modelo o todavía como el slug crudo.
        if ($tenant instanceof Model) {
            return $tenant instanceof Empresa ? $tenant->slug : null;
        }

        return is_string($tenant) && $tenant !== '' ? $tenant : null;
    }
}
