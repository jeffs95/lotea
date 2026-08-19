<?php

namespace App\Providers;

use App\Listeners\SincronizarEmpresaActiva;
use Filament\Events\TenantSet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(TenantSet::class, SincronizarEmpresaActiva::class);

        // Con el locale 'es' a secas, Q95,700.00 se imprime "95.700,00 GTQ".
        // Guatemala usa el formato anglosajón para los números.
        Number::useLocale('es_GT');
        Number::useCurrency('GTQ');

        // Que reviente temprano en desarrollo. No activamos preventLazyLoading
        // porque pelea con la carga diferida de las tablas de Filament.
        Model::preventSilentlyDiscardingAttributes($this->app->isLocal());
        Model::preventAccessingMissingAttributes($this->app->isLocal());
    }
}
