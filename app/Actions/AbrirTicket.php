<?php

namespace App\Actions;

use App\Models\Ticket;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/**
 * Abre un ticket capturando el contexto por su cuenta.
 *
 * El cliente escribe dos líneas; el resto lo pone el sistema, que es lo que de
 * verdad sirve para resolver.
 */
class AbrirTicket
{
    public function ejecutar(User $usuario, array $datos, ?Request $request = null): Ticket
    {
        return Ticket::create([
            'user_id' => $usuario->id,
            'numero' => $this->siguienteNumero(),
            'asunto' => $datos['asunto'],
            'mensaje' => $datos['mensaje'],
            'contexto' => $this->capturarContexto($usuario, $datos, $request),
            'estado' => 'abierto',
        ]);
    }

    protected function siguienteNumero(): string
    {
        $ultimo = Ticket::where('empresa_id', Tenancy::empresaId())->count();

        return 'T-'.str_pad((string) ($ultimo + 1), 4, '0', STR_PAD_LEFT);
    }

    /** @return array<string, string|null> */
    protected function capturarContexto(User $usuario, array $datos, ?Request $request): array
    {
        return array_filter([
            'rol' => $usuario->getRoleNames()->implode(', ') ?: 'sin rol',
            'pantalla' => $datos['pantalla'] ?? null,
            'navegador' => $request?->userAgent(),
            'ip' => $request?->ip(),
            'reportado_en' => now()->toDateTimeString(),
        ]);
    }
}
