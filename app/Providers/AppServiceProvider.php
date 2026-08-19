<?php

namespace App\Providers;

use App\Listeners\RegistrarUltimoAcceso;
use App\Listeners\SincronizarEmpresaActiva;
use Illuminate\Auth\Events\Login;
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
        Event::listen(Login::class, RegistrarUltimoAcceso::class);

        // Con el locale 'es' a secas, Q95,700.00 se imprime "95.700,00 GTQ".
        // Guatemala usa el formato anglosajón para los números.
        Number::useLocale('es_GT');
        Number::useCurrency('GTQ');

        // Que reviente temprano en desarrollo Y en los tests: una asignación
        // silenciosa que la suite deja pasar aparece en producción. No
        // activamos preventLazyLoading porque pelea con las tablas de Filament.
        $estricto = $this->app->isLocal() || $this->app->runningUnitTests();

        Model::preventSilentlyDiscardingAttributes($estricto);
        Model::preventAccessingMissingAttributes($estricto);
    }
}
