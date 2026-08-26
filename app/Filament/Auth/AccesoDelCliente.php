<?php

namespace App\Filament\Auth;

use App\Models\Empresa;

/**
 * La entrada del concesionario: /app/login
 *
 * Aquí todavía no se sabe de qué empresa es quien escribe, porque la URL no
 * lleva su slug hasta después de entrar. Así que la marca es de Lotea y no del
 * cliente; la suya aparece en cuanto pisa su panel.
 */
class AccesoDelCliente extends Acceso
{
    public function getHeading(): string
    {
        return 'Entre a su cuenta';
    }

    public function portada(): array
    {
        return [
            'titulo' => 'Su patio completo, en una pantalla.',
            'lema' => 'Del martillo de la subasta a la entrega de llaves.',
            'puntos' => [
                'Cada unidad con su costo de verdad: subasta, flete, impuestos y taller.',
                'Lo vendido, lo cobrado y lo que falta por cobrar, al día.',
                'Su catálogo público al día sin tener que actualizarlo aparte.',
            ],
            'color' => Empresa::COLOR_POR_DEFECTO,
            'etiqueta' => 'Concesionarios',
            // No hay recuperación de contraseña, así que quien la olvida se
            // queda mirando la pantalla. Al menos que sepa a quién pedirla.
            'nota' => '¿Olvidó su contraseña? Pídale a quien administra su concesionario que se la restablezca.',
        ];
    }
}
