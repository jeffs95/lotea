<?php

namespace App\Actions;

use App\Models\Arqueo;
use App\Models\Caja;
use Illuminate\Support\Facades\Auth;

/**
 * Deja constancia del conteo físico contra el saldo del sistema.
 *
 * La diferencia se registra tal cual: no se ajusta el saldo por detrás. Si
 * falta dinero, tiene que verse que faltó.
 */
class ArquearCaja
{
    public function ejecutar(Caja $caja, string|float $saldoContado, ?string $justificacion = null): Arqueo
    {
        $sistema = (string) $caja->saldo;
        $contado = (string) $saldoContado;

        return Arqueo::create([
            'caja_id' => $caja->id,
            'user_id' => Auth::id(),
            'realizado_en' => now(),
            'saldo_sistema' => $sistema,
            'saldo_contado' => $contado,
            'diferencia' => bcsub($contado, $sistema, 2),
            'justificacion' => $justificacion,
        ]);
    }
}
