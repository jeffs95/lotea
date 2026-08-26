<?php

namespace App\Filament\Auth;

/**
 * La entrada de Lotea: /central/login
 *
 * En azul y con otro texto a propósito. Las dos rutas se parecen lo suficiente
 * para equivocarse, y quien llegue aquí buscando su concesionario tiene que
 * darse cuenta antes de escribir su contraseña tres veces.
 */
class AccesoCentral extends Acceso
{
    public function getHeading(): string
    {
        return 'Entre a la central';
    }

    public function portada(): array
    {
        return [
            'titulo' => 'La operación del negocio.',
            'lema' => 'Concesionarios, planes y cobros.',
            'puntos' => [
                'Alta de concesionarios y el plan que le toca a cada uno.',
                'Quién está al día, quién debe y a quién hay que suspender.',
                'Entrar al panel de un cliente para darle soporte.',
            ],
            'color' => '#6366f1',
            'etiqueta' => 'Uso interno',
            'nota' => 'Esta entrada es del equipo de Lotea. Si usted administra un concesionario, la suya es /app.',
        ];
    }
}
