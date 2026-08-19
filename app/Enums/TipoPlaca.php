<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * El tipo de placa guatemalteca, por la letra con la que empieza.
 *
 * No es un dato decorativo: el uso al que está inscrito el vehículo cambia a
 * quién se le puede vender y a qué precio. Una placa de alquiler o comercial
 * no vale lo mismo que una particular.
 *
 * OJO: confirmá los códigos con la SAT antes de venderle esto a un cliente.
 * Están aquí, en un solo lugar, justamente para poder corregirlos sin tocar
 * nada más.
 */
enum TipoPlaca: string implements HasLabel
{
    case Particular = 'P';
    case Comercial = 'C';
    case Motocicleta = 'M';
    case Alquiler = 'A';
    case UsoAgricola = 'U';
    case Oficial = 'O';
    case Diplomatica = 'CD';
    case Traslado = 'T';
    case Otro = 'otro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Particular => 'P · Particular',
            self::Comercial => 'C · Comercial',
            self::Motocicleta => 'M · Motocicleta',
            self::Alquiler => 'A · Alquiler',
            self::UsoAgricola => 'U · Uso agrícola o industrial',
            self::Oficial => 'O · Oficial',
            self::Diplomatica => 'CD · Cuerpo diplomático',
            self::Traslado => 'T · Traslado o temporal',
            self::Otro => 'Otro',
        };
    }

    /** El tipo que corresponde por defecto a este tipo de vehículo. */
    public static function sugeridaPara(TipoVehiculo $vehiculo): self
    {
        return match ($vehiculo) {
            TipoVehiculo::Motocicleta => self::Motocicleta,
            TipoVehiculo::Camion => self::Comercial,
            TipoVehiculo::Automovil => self::Particular,
        };
    }

    /** Lo deduce de la placa misma: de "P123ABC" sale Particular. */
    public static function desdeLaPlaca(?string $placa): ?self
    {
        if (blank($placa)) {
            return null;
        }

        $limpia = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa));

        foreach (['CD'] as $doble) {
            if (str_starts_with($limpia, $doble)) {
                return self::tryFrom($doble);
            }
        }

        return self::tryFrom(substr($limpia, 0, 1));
    }

    public static function opciones(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->getLabel()])->all();
    }
}
