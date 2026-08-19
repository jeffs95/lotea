<?php

namespace App\Actions;

use App\Models\LecturaIa;
use App\Support\TarifaDeIa;
use Illuminate\Support\Facades\Auth;

/**
 * Deja constancia de cada lectura y de lo que costó.
 *
 * Es lo que permite cobrar el add-on con números en la mano: cuánto consume
 * cada cliente al mes y si el precio que se le puso deja margen.
 */
class RegistrarLecturaIa
{
    /** @param array{tokens_entrada: int, tokens_salida: int, documentos: int, modelo: string} $consumo */
    public function exitosa(array $consumo, int $camposLeidos): LecturaIa
    {
        return LecturaIa::create([
            'user_id' => Auth::id(),
            'modelo' => $consumo['modelo'],
            'documentos' => $consumo['documentos'],
            'tokens_entrada' => $consumo['tokens_entrada'],
            'tokens_salida' => $consumo['tokens_salida'],
            'costo_usd' => TarifaDeIa::costo($consumo['tokens_entrada'], $consumo['tokens_salida']),
            'campos_leidos' => $camposLeidos,
            'exitosa' => true,
        ]);
    }

    /** Los intentos fallidos también se registran: a veces igual se pagan. */
    public function fallida(string $error, int $documentos = 1): LecturaIa
    {
        return LecturaIa::create([
            'user_id' => Auth::id(),
            'modelo' => (string) config('services.openrouter.modelo'),
            'documentos' => $documentos,
            'exitosa' => false,
            'error' => mb_substr($error, 0, 190),
        ]);
    }
}
