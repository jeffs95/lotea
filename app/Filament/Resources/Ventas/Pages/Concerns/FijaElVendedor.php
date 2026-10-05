<?php

namespace App\Filament\Resources\Ventas\Pages\Concerns;

/**
 * La venta de un vendedor externo queda a su nombre.
 *
 * En el formulario el campo ya viene fijo, pero eso es una propiedad del
 * navegador y el navegador lo pone quien está del otro lado. Aquí es donde de
 * verdad se decide: si quien guarda no puede ver las ventas de los demás,
 * tampoco puede registrar una a nombre de otro, venga como venga el envío.
 *
 * Importa porque de ese campo cuelga la comisión: dejarlo abierto es dejar que
 * alguien le cargue —o se quite— un pago a un compañero.
 */
trait FijaElVendedor
{
    protected function conElVendedorQueCorresponde(array $datos): array
    {
        if (auth()->user()?->can('ver_ventas_ajenas')) {
            return $datos;
        }

        $datos['vendedor_id'] = auth()->id();

        return $datos;
    }
}
