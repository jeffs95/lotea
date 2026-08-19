<?php

use App\Http\Controllers\Portal\CatalogoController;
use App\Http\Controllers\Portal\LeadController;
use App\Http\Controllers\Portal\SitemapController;
use App\Http\Middleware\ResolverEmpresaDelPortal;
use Illuminate\Support\Facades\Route;

/**
 * El portal se sirve dos veces: en el dominio propio de cada cliente
 * (portal.*) y bajo /v/{slug} para desarrollo y para los que todavía no
 * compran dominio (portal.demo.*). Mismos controladores, mismas vistas.
 */
$rutas = function () {
    Route::get('/', [CatalogoController::class, 'inicio'])->name('inicio');
    Route::get('/vehiculos', [CatalogoController::class, 'catalogo'])->name('catalogo');
    Route::get('/vehiculos/{slug}', [CatalogoController::class, 'unidad'])->name('unidad');
    Route::post('/contacto', [LeadController::class, 'store'])->name('lead');
    Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
    Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
};

Route::middleware(ResolverEmpresaDelPortal::class)->group(function () use ($rutas) {
    Route::name('portal.demo.')->prefix('v/{empresaSlug}')->group($rutas);
    Route::name('portal.')->group($rutas);
});
