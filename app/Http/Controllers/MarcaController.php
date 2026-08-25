<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Support\Tenancy;
use Symfony\Component\HttpFoundation\Response;

/**
 * El logo y el favicon del concesionario.
 *
 * Son públicos —aparecen en su portal, que es de cara a la calle— pero viven
 * en el mismo disco que el resto de los archivos, así que también pasan por
 * aquí en vez de servirse directo.
 */
class MarcaController extends Controller
{
    public const TIPOS = [
        'logo' => 'logo_claro_path',
        'logo-original' => 'logo_path',
        'logo-oscuro' => 'logo_oscuro_path',
        'isotipo' => 'isotipo_path',
        'portada' => 'portada_path',
        'favicon' => 'favicon_path',
    ];

    public function __invoke(string $slug, string $tipo): Response
    {
        // Sin filtro: el portal público no tiene empresa activa, y una empresa
        // no es dato de otra empresa.
        $empresa = Tenancy::sinFiltro(fn () => Empresa::firstWhere('slug', $slug));

        abort_unless($empresa, 404);

        // El icono de la pestaña se dibuja al vuelo: son dos letras y un
        // rectángulo, no hay archivo que guardar.
        if ($tipo === 'pestana') {
            return response($empresa->favicon_svg, 200, [
                'Content-Type' => 'image/svg+xml',
                'Cache-Control' => 'public, max-age=604800',
            ]);
        }

        abort_unless(isset(self::TIPOS[$tipo]), 404);

        $archivo = $empresa->archivoDeMarcaLocal(self::TIPOS[$tipo]);

        abort_unless($archivo, 404);

        // Un logo cambia muy de vez en cuando, y cuando cambia es otro archivo
        // con otro nombre, así que se puede cachear tranquilo.
        return response()->file($archivo)->setMaxAge(604800)->setPublic();
    }
}
