<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        then: function () {
            Route::middleware('web')->group(base_path('routes/portal.php'));
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * En Heroku el TLS lo termina el router y a PHP le llega la petición por
         * HTTP interno, avisando con «X-Forwarded-Proto». Sin esto Laravel cree
         * que la conexión es en claro y arma sus enlaces con «http://», que en
         * un dominio con HSTS el navegador bloquea: el formulario de acceso se
         * queda mudo sin decir por qué.
         *
         * También es lo que hace que el rastro de auditoría guarde la IP del
         * visitante y no la del proxy, que sería la misma para todo el mundo.
         *
         * Se confía en cualquiera porque las IPs del router de Heroku no son
         * fijas, y al dyno no se llega sino pasando por él.
         */
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
