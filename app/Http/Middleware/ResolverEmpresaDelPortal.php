<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decide de qué concesionario es el portal que se está pidiendo.
 *
 * En producción cada cliente tiene su dominio; en desarrollo se entra por
 * /v/{slug}. Las dos formas terminan en lo mismo: la empresa activa fijada
 * antes de que se toque un solo registro.
 */
class ResolverEmpresaDelPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $empresa = $this->porSlugDeLaRuta($request) ?? $this->porDominio($request);

        // El dominio de Lotea no es el portal de nadie: quien escribe app.lotea.dev
        // a secas viene a entrar a su cuenta, no a ver un catálogo. Antes se
        // topaba con un 404 que no le decía a dónde ir.
        if ($empresa === null && $this->esLaPuertaDelSistema($request)) {
            return redirect()->to(route('filament.admin.tenant'));
        }

        // Incluye a los suspendidos: mientras no paguen, su sitio no responde.
        // Y no se les redirige a ninguna parte: el 404 es la palanca de cobro.
        abort_if($empresa === null || ! $empresa->puedeOperar(), 404);

        Tenancy::usar($empresa);
        View::share('empresa', $empresa);

        return $next($request);
    }

    /**
     * Si esto es la raíz pelada de un host que no es de ningún cliente.
     *
     * Solo la raíz: una URL de portal cualquiera en el dominio de Lotea sigue
     * siendo un 404, porque no existe y esconderlo detrás de un redirect haría
     * más difícil ver el error el día que un enlace salga mal armado.
     */
    protected function esLaPuertaDelSistema(Request $request): bool
    {
        return $request->route('empresaSlug') === null && $request->path() === '/';
    }

    protected function porSlugDeLaRuta(Request $request): ?Empresa
    {
        $slug = $request->route('empresaSlug');

        return $slug ? Empresa::firstWhere('slug', $slug) : null;
    }

    protected function porDominio(Request $request): ?Empresa
    {
        return Empresa::firstWhere('dominio', $request->getHost());
    }
}
