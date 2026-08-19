<?php

namespace App\Services;

use App\Filament\Resources\Unidades\Schemas\UnidadForm;
use Illuminate\Support\Str;

/**
 * Filtra lo que devolvió el modelo antes de dejarlo entrar al formulario.
 *
 * El modelo se equivoca y a veces inventa: un VIN de 15 caracteres, un año
 * 2045, una transmisión que no existe en nuestra lista. Todo lo que no pasa
 * por aquí se descarta en silencio en lugar de ensuciar la ficha.
 */
class ValidadorDeDatosLeidos
{
    /** @param array<string, mixed> $datos */
    public static function limpiar(array $datos): array
    {
        return array_filter([
            'vin' => self::vin($datos['vin'] ?? null),
            'marca' => self::texto($datos['marca'] ?? null, 60),
            'linea' => self::texto($datos['linea'] ?? null, 60),
            'version' => self::texto($datos['version'] ?? null, 60),
            'anio' => self::anio($datos['anio'] ?? null),
            'color' => self::texto($datos['color'] ?? null, 40),
            'motor' => self::texto($datos['motor'] ?? null, 40),
            'cilindros' => self::entero($datos['cilindros'] ?? null, 2, 16),
            'puertas' => self::entero($datos['puertas'] ?? null, 2, 6),
            'odometro' => self::entero($datos['odometro'] ?? null, 0, 2_000_000),
            'odometro_unidad' => self::deLista($datos['odometro_unidad'] ?? null, ['mi', 'km']),
            'transmision' => self::deLista($datos['transmision'] ?? null, array_keys(UnidadForm::TRANSMISIONES)),
            'combustible' => self::deLista($datos['combustible'] ?? null, array_keys(UnidadForm::COMBUSTIBLES)),
            'traccion' => self::deLista($datos['traccion'] ?? null, array_keys(UnidadForm::TRACCIONES)),
            'carroceria' => self::deLista($datos['carroceria'] ?? null, array_keys(UnidadForm::CARROCERIAS)),
            'tipo_titulo' => self::deLista($datos['tipo_titulo'] ?? null, array_keys(UnidadForm::TIPOS_TITULO)),
            'tipo_dano' => self::texto($datos['tipo_dano'] ?? null, 80),
            // La placa es un identificador, no un nombre: no se le aplica
            // capitalización o P123ABC termina como P123Abc.
            'placa' => self::identificador($datos['placa'] ?? null, 20),
        ], fn ($valor) => $valor !== null && $valor !== '');
    }

    /** 17 caracteres, sin I, O ni Q. Si no cumple, no es un VIN. */
    protected static function vin(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $limpio = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $valor));

        return preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $limpio) ? $limpio : null;
    }

    protected static function identificador(mixed $valor, int $largo): ?string
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        return Str::limit(Str::upper(Str::squish($valor)), $largo, '');
    }

    protected static function texto(mixed $valor, int $largo): ?string
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        $limpio = Str::squish($valor);

        // Los documentos vienen en mayúsculas sostenidas y en pantalla se ve mal.
        if ($limpio === Str::upper($limpio) && Str::length($limpio) > 3) {
            $limpio = Str::title(Str::lower($limpio));
        }

        return Str::limit($limpio, $largo, '');
    }

    protected static function anio(mixed $valor): ?int
    {
        return self::entero($valor, 1980, (int) date('Y') + 2);
    }

    protected static function entero(mixed $valor, int $minimo, int $maximo): ?int
    {
        if (! is_numeric($valor)) {
            return null;
        }

        $numero = (int) $valor;

        return $numero >= $minimo && $numero <= $maximo ? $numero : null;
    }

    /** @param array<int, string> $permitidos */
    protected static function deLista(mixed $valor, array $permitidos): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $limpio = Str::lower(trim($valor));

        return in_array($limpio, $permitidos, true) ? $limpio : null;
    }
}
