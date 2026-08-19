<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/**
 * El QR del parabrisas. Ruta corta a propósito: el código va impreso y
 * alguien lo va a teclear a mano alguna vez.
 */
Route::get('/u/{codigo}', \App\Http\Controllers\EscaneoController::class)->name('escaneo');
