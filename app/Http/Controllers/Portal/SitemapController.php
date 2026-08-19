<?php

namespace App\Http\Controllers\Portal;

use App\Models\Unidad;
use App\Support\PortalUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Sitemap y robots por concesionario.
 *
 * El inventario rota rápido: un carro que se vendió deja de existir para
 * Google en cuanto sale del sitemap, y uno nuevo entra el mismo día.
 */
class SitemapController
{
    public function sitemap(Request $request): Response
    {
        $empresa = $request->attributes->get('empresa') ?? view()->shared('empresa');

        $urls = collect([
            ['loc' => PortalUrl::inicio($empresa), 'prioridad' => '1.0'],
            ['loc' => PortalUrl::catalogo($empresa), 'prioridad' => '0.9'],
        ]);

        $unidades = Unidad::where('publicado', true)
            ->publicables()
            ->whereNotNull('slug')
            ->get(['slug', 'updated_at']);

        foreach ($unidades as $unidad) {
            $urls->push([
                'loc' => PortalUrl::unidad($empresa, $unidad->slug),
                'prioridad' => '0.8',
                'fecha' => $unidad->updated_at?->toAtomString(),
            ]);
        }

        $xml = view('portal.sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(Request $request): Response
    {
        $empresa = view()->shared('empresa');

        $cuerpo = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Sitemap: '.PortalUrl::ruta('sitemap', $empresa),
            '',
        ]);

        return response($cuerpo, 200, ['Content-Type' => 'text/plain']);
    }
}
