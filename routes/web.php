<?php

use App\Http\Controllers\EscaneoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/**
 * El QR del parabrisas. Ruta corta a propósito: el código va impreso y
 * alguien lo va a teclear a mano alguna vez.
 */
Route::get('/u/{codigo}', EscaneoController::class)->name('escaneo');
