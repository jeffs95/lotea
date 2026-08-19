<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En el panel central no hay empresa activa.
 *
 * Si quedara contexto de una sesión anterior, las consultas de modelos con
 * scope devolverían solo los datos de ese cliente y las métricas globales
 * saldrían mal sin avisar.
 */
class LimpiarContextoDeEmpresa
{
    public function handle(Request $request, Closure $next): Response
    {
        Tenancy::olvidar();

        return $next($request);
    }
}
