<?php

namespace App\Providers;

use App\Listeners\RegistrarUltimoAcceso;
use App\Listeners\SincronizarEmpresaActiva;
use Filament\Events\TenantSet;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Cuántas consultas puede mandar una misma persona desde el portal.
     *
     * Un comprador manda una o dos; cinco por minuto ya es un bot. El límite
     * por hora es el que de verdad protege: sin él, un script paciente igual
     * llena el CRM del cliente en una tarde.
     */
    protected function limitarEnviosDelPortal(): void
    {
        RateLimiter::for('leads', fn (Request $peticion) => [
            Limit::perMinute(5)->by($peticion->ip()),
            Limit::perHour(20)->by($peticion->ip()),
        ]);
    }

    public function boot(): void
    {
        $this->limitarEnviosDelPortal();

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
