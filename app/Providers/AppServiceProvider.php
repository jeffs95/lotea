<?php

namespace App\Providers;

use App\Listeners\RegistrarUltimoAcceso;
use App\Listeners\SincronizarEmpresaActiva;
use App\Support\AlmacenDeArchivos;
use App\Support\ModoSoporte;
use App\Support\Tenancy;
use Filament\Events\TenantSet;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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

        // Al borrar un archivo hay que borrar también su copia local, o el
        // disco se llena de fotos de carros que ya no existen.
        Media::deleted(fn (Media $media) => AlmacenDeArchivos::olvidarTodoDe($media));

        /*
         * Durante una sesión de soporte, el operador de Lotea pasa las
         * políticas del panel del cliente.
         *
         * No tiene roles en esa empresa —no es de la casa— así que sin esto
         * entraría al panel y no podría abrir nada, que es igual de inútil que
         * el 404 de antes.
         *
         * Se devuelve null y no false cuando no aplica: null deja que la
         * política siga su curso normal, false la cortaría en seco.
         */
        Gate::before(function ($usuario, string $habilidad) {
            return ModoSoporte::activo() && Tenancy::hayEmpresa()
                && ModoSoporte::esLaEmpresaAbierta(Tenancy::empresaId())
                    ? true
                    : null;
        });

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
