<?php

namespace App\Providers;

use App\Listeners\RegistrarUltimoAcceso;
use App\Listeners\SincronizarEmpresaActiva;
use App\Support\AlmacenDeArchivos;
use Filament\Events\TenantSet;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
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
        Media::deleted(fn (Media $media) => Storage::disk(AlmacenDeArchivos::DISCO_CACHE)
            ->deleteDirectory((string) $media->getKey()));

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
