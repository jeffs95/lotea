<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Qué clase de vehículo es.
 *
 * No es un adorno: una moto no tiene puertas, ni color de interior, ni
 * tracción 4x4, y en cambio lo primero que pregunta el cliente es la
 * cilindrada. Pedir la ficha equivocada hace que el vendedor deje campos
 * vacíos o, peor, invente.
 */
enum TipoVehiculo: string implements HasIcon, HasLabel
{
    case Automovil = 'automovil';
    case Motocicleta = 'motocicleta';
    case Camion = 'camion';

    public function getLabel(): string
    {
        return match ($this) {
            self::Automovil => 'Automóvil',
            self::Motocicleta => 'Motocicleta',
            self::Camion => 'Camión / pesado',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Automovil => 'heroicon-o-truck',
            self::Motocicleta => 'heroicon-o-bolt',
            self::Camion => 'heroicon-o-truck',
        };
    }

    /** Las carrocerías que tienen sentido para este tipo. */
    public function carrocerias(): array
    {
        return match ($this) {
            self::Automovil => [
                'sedan' => 'Sedán',
                'suv' => 'SUV',
                'pickup' => 'Pick-up',
                'hatchback' => 'Hatchback',
                'coupe' => 'Coupé',
                'convertible' => 'Convertible',
                'van' => 'Van',
                'otro' => 'Otro',
            ],
            self::Motocicleta => [
                'scooter' => 'Scooter',
                'deportiva' => 'Deportiva',
                'naked' => 'Naked / calle',
                'doble_proposito' => 'Doble propósito',
                'cross' => 'Cross / enduro',
                'touring' => 'Touring',
                'custom' => 'Custom / chopper',
                'tres_ruedas' => 'Tres ruedas',
                'otro' => 'Otro',
            ],
            self::Camion => [
                'cabezal' => 'Cabezal',
                'furgon' => 'Furgón',
                'volteo' => 'Volteo',
                'plataforma' => 'Plataforma',
                'cisterna' => 'Cisterna',
                'bus' => 'Bus',
                'otro' => 'Otro',
            ],
        };
    }

    /** Transmisiones propias del tipo: una moto no trae CVT de automóvil. */
    public function transmisiones(): array
    {
        return match ($this) {
            self::Motocicleta => [
                'manual' => 'Manual',
                'semiautomatica' => 'Semiautomática',
                'automatica' => 'Automática (scooter)',
            ],
            default => [
                'automatica' => 'Automática',
                'manual' => 'Manual',
                'cvt' => 'CVT',
            ],
        };
    }

    /** Campos que no existen en este tipo y no hay que pedir. */
    public function camposQueNoAplican(): array
    {
        return match ($this) {
            self::Motocicleta => ['puertas', 'color_interior', 'traccion'],
            self::Camion => ['color_interior'],
            self::Automovil => [],
        };
    }

    public function aplica(string $campo): bool
    {
        return ! in_array($campo, $this->camposQueNoAplican(), true);
    }

    public function esMoto(): bool
    {
        return $this === self::Motocicleta;
    }

    /** La cilindrada manda en motos; en autos es un dato más. */
    public function destacaCilindrada(): bool
    {
        return $this === self::Motocicleta;
    }

    public static function opciones(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->getLabel()])->all();
    }

    /** Todas las carrocerías de todos los tipos, para validar y para filtros. */
    public static function todasLasCarrocerias(): array
    {
        return collect(self::cases())
            ->flatMap(fn (self $tipo) => $tipo->carrocerias())
            ->all();
    }

    public static function todasLasTransmisiones(): array
    {
        return collect(self::cases())
            ->flatMap(fn (self $tipo) => $tipo->transmisiones())
            ->all();
    }
}
