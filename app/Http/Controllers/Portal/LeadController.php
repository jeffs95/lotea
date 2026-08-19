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
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'telefono' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'mensaje' => ['nullable', 'string', 'max:1000'],
            'unidad_id' => ['nullable', 'integer'],
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
}
