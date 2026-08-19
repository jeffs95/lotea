<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder as ConstructorDeConsulta;
use InvalidArgumentException;

/**
 * Correlativos que se leen del propio campo, nunca del id de la tabla.
 *
 * El id es global a toda la plataforma: usarlo como número visible haría que
 * el primer carro de un cliente nuevo se llamara 0087 porque otros
 * concesionarios ya dieron de alta 86. Aquí se busca el número más alto que
 * esta empresa ya usó en la columna y se le suma uno.
 *
 * Se toma sólo el tramo final de dígitos, así que un prefijo con números
 * ("A1-0007") no contamina la cuenta, y un valor sin dígitos ("DEMO") no la
 * rompe.
 *
 * Dos altas simultáneas pueden pedir el mismo número. El unique
 * (empresa_id, columna) de la base rechaza la segunda en vez de dejar pasar un
 * duplicado, que es el resultado correcto: mejor un error al guardar que dos
 * carros llamándose igual.
 */
class Correlativo
{
    public static function siguiente(ConstructorDeConsulta $consulta, string $columna, int $digitos = 4): string
    {
        if (! preg_match('/^[a-z_][a-z0-9_]*$/', $columna)) {
            throw new InvalidArgumentException("«{$columna}» no es un nombre de columna.");
        }

        $ultimo = (int) $consulta
            ->selectRaw("coalesce(max(coalesce(substring({$columna} from '(\\d+)\$'), '0')::bigint), 0) as ultimo")
            ->value('ultimo');

        return str_pad((string) ($ultimo + 1), $digitos, '0', STR_PAD_LEFT);
    }
}
