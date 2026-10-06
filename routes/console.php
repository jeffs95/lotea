<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * El respaldo de cada noche.
 *
 * A las tres de la mañana: a esa hora nadie está cargando una venta, y si el
 * volcado tarda no le quita velocidad a nadie. Hace falta que algo corra
 * «schedule:work» para que esto suceda; en el compose es el servicio
 * «reloj».
 */
Schedule::command('lotea:respaldar')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();
