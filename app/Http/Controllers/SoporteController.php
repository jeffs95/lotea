<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\User;
use App\Support\ModoSoporte;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Abre y cierra el panel de un cliente para dar soporte.
 *
 * Cada entrada y cada salida quedan anotadas en el rastro del concesionario.
 * No es para vigilar a nadie: es lo que permite responder si un cliente
 * reclama que le cambiaron algo. Sin registro, es su palabra contra la nuestra.
 */
class SoporteController extends Controller
{
    public function entrar(Empresa $empresa): RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->to(route('filament.central.auth.login'));
        }

        abort_unless($this->esOperador(), 403);
        abort_unless($empresa->puedeOperar(), 404, 'Ese concesionario está suspendido o dado de baja.');

        ModoSoporte::entrar($empresa);

        $this->anotar($empresa, 'Lotea entró a dar soporte');

        return redirect()->to(
            route('filament.admin.pages.dashboard', ['tenant' => $empresa->slug])
        );
    }

    public function salir(): RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->to(route('filament.central.auth.login'));
        }

        $empresa = ModoSoporte::empresa();

        if ($empresa) {
            $this->anotar($empresa, 'Lotea salió del soporte');
        }

        ModoSoporte::salir();

        return redirect()->to(route('filament.central.pages.dashboard'));
    }

    /** Se pregunta a la base: Livewire rehidrata el modelo sin todas sus columnas. */
    protected function esOperador(): bool
    {
        return (bool) User::query()
            ->whereKey(Auth::id())
            ->where('activo', true)
            ->value('es_operador');
    }

    /**
     * Deja constancia en el rastro de ese concesionario.
     *
     * Se escribe con la empresa fijada para que la anotación quede en su
     * historial y no suelta: el dueño la ve en su propia pantalla de auditoría.
     */
    protected function anotar(Empresa $empresa, string $descripcion): void
    {
        Tenancy::comoEmpresa($empresa, function () use ($empresa, $descripcion) {
            $actividad = activity('soporte')
                ->performedOn($empresa)
                ->causedBy(Auth::user())
                ->withProperties(['empresa' => $empresa->getFilamentName()])
                ->log($descripcion);

            // El helper no pasa por el trait que rellena la empresa, y sin ella
            // la anotación quedaría suelta y fuera del historial del cliente.
            $actividad?->forceFill(['empresa_id' => $empresa->getKey()])->save();
        });
    }
}
