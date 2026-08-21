<?php

namespace App\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Avatares de iniciales dibujados aquí mismo.
 *
 * Filament trae por defecto ui-avatars.com, que le manda el nombre de cada
 * usuario y de cada concesionario a un servidor ajeno en cada carga de
 * pantalla. No hace falta: son dos letras y un círculo. Además el panel deja
 * de depender de que ese servicio esté en pie, y el avatar sale del color de
 * marca del cliente en vez de un gris genérico.
 *
 * El SVG va como data URI porque Filament espera una URL, no marcado.
 */
class AvatarDeIniciales implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $svg = $this->svg(
            static::de(Filament::getNameForDefaultAvatar($record)),
            MarcaDelCliente::color(),
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Dos letras: la del nombre y la del apellido, o las dos primeras.
     *
     * Se descarta todo lo que no sea letra antes de partir. Los nombres reales
     * traen paréntesis y puntos —«Jeferson (dueño)», «Autos del Valle, S.A.»—
     * y un avatar que dijera «J(» no se lo puede enseñar a nadie.
     */
    public static function de(string $nombre): string
    {
        $palabras = str($nombre)
            ->replaceMatches('/[^\p{L}\s]+/u', ' ')
            ->squish()
            ->explode(' ')
            ->filter()
            ->values();

        if ($palabras->isEmpty()) {
            return '?';
        }

        $letras = $palabras->count() > 1
            ? mb_substr($palabras->first(), 0, 1).mb_substr($palabras->last(), 0, 1)
            : mb_substr($palabras->first(), 0, 2);

        return mb_strtoupper($letras);
    }

    protected function svg(string $iniciales, string $fondo): string
    {
        $texto = htmlspecialchars($iniciales, ENT_QUOTES | ENT_XML1);

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">
                <rect width="64" height="64" fill="{$fondo}"/>
                <text x="50%" y="50%" dy=".35em" fill="#ffffff" text-anchor="middle"
                      font-family="system-ui, -apple-system, sans-serif"
                      font-size="26" font-weight="600">{$texto}</text>
            </svg>
            SVG;
    }
}
