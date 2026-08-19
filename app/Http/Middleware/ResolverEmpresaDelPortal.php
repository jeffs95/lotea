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

        // Incluye a los suspendidos: mientras no paguen, su sitio no responde.
        abort_if($empresa === null || ! $empresa->puedeOperar(), 404);

        Tenancy::usar($empresa);
        View::share('empresa', $empresa);

        return $next($request);
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
