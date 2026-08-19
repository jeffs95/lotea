<?php

namespace App\Http\Controllers\Portal;

use App\Models\Lead;
use App\Models\Unidad;
use App\Support\PortalUrl;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Recibe las consultas del portal y las mete al CRM.
 *
 * El lead se guarda aunque el vendedor no esté; lo que no puede pasar es que
 * alguien levante la mano y se pierda en un correo.
 */
class LeadController
{
    /** Campo trampa: está oculto, así que solo un bot lo llena. */
    public const HONEYPOT = 'sitio_web';

    /** Segundos mínimos que le toma a una persona llenar el formulario. */
    public const SEGUNDOS_MINIMOS = 3;

    public function store(Request $request): RedirectResponse
    {
        if ($this->pareceUnBot($request)) {
            // Se responde como si hubiera funcionado: si el bot recibe un
            // error, prueba otra cosa; si cree que pasó, se va tranquilo.
            return back()->with('lead_enviado', true);
        }

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120', 'not_regex:/https?:\/\//i'],
            'telefono' => ['required', 'string', 'max:30', 'min:8'],
            'email' => ['nullable', 'email', 'max:120'],
            'mensaje' => ['nullable', 'string', 'max:1000'],
            'unidad_id' => ['nullable', 'integer'],
        ], [
            'nombre.not_regex' => 'El nombre no puede contener enlaces.',
            'telefono.min' => 'Ese teléfono parece incompleto.',
        ]);

        // El scope de empresa ya filtra: si el id es de otro concesionario,
        // llega null y el lead queda sin unidad en vez de cruzar datos.
        $unidad = filled($datos['unidad_id'] ?? null)
            ? Unidad::find($datos['unidad_id'])
            : null;

        Lead::create([
            'unidad_id' => $unidad?->id,
            'sucursal_id' => $unidad?->sucursal_id,
            'nombre' => $datos['nombre'],
            'telefono' => $datos['telefono'],
            'email' => $datos['email'] ?? null,
            'mensaje' => $datos['mensaje'] ?? null,
            'origen' => 'portal',
        ]);

        return back()->with('lead_enviado', true);
    }

    /**
     * Tres señales que una persona no produce.
     *
     * El campo trampa lleno, el formulario enviado en menos de tres segundos,
     * o un mensaje con varios enlaces: nada de eso lo hace un comprador que
     * está viendo un carro.
     */
    protected function pareceUnBot(Request $request): bool
    {
        if (filled($request->input(self::HONEYPOT))) {
            return true;
        }

        $abierto = (int) $request->input('_t', 0);

        if ($abierto > 0 && (now()->timestamp - $abierto) < self::SEGUNDOS_MINIMOS) {
            return true;
        }

        return preg_match_all('/https?:\/\//i', (string) $request->input('mensaje')) >= 2;
    }
}
