<?php

use App\Http\Controllers\ArchivoController;
use App\Http\Controllers\EscaneoController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\SoporteController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/**
 * El QR del parabrisas. Ruta corta a propósito: el código va impreso y
 * alguien lo va a teclear a mano alguna vez.
 */
Route::get('/u/{codigo}', EscaneoController::class)->name('escaneo');

/**
 * Las fotos y los documentos de las unidades.
 *
 * Viven en el FTP, que no tiene URL pública, así que pasan por aquí. El
 * controlador decide quién puede ver cada uno: las fotos de lo publicado son
 * del catálogo, lo demás es del concesionario.
 */
Route::get('/archivo/{media}/{conversion?}', ArchivoController::class)
    ->whereNumber('media')
    ->whereIn('conversion', ['miniatura', 'web'])
    ->name('archivo');

/** El logo y el favicon del concesionario, que viven en el mismo disco. */
Route::get('/marca/{slug}/{tipo}', MarcaController::class)->name('marca');

/**
 * El acceso de Lotea al panel de un cliente, para dar soporte.
 *
 * Fuera del panel central a propósito: el operador termina en el panel del
 * concesionario, que es otro panel de Filament.
 */
Route::middleware('web')->group(function () {
    // Primero la salida: si no, «salir» entraría como el slug de un
    // concesionario que no existe y respondería 404.
    Route::get('/soporte/salir', [SoporteController::class, 'salir'])->name('soporte.salir');

    Route::get('/soporte/{empresa:slug}', [SoporteController::class, 'entrar'])->name('soporte.entrar');
});
