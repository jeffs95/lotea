<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * La cuenta con la que Lotea entra a su propio panel.
 *
 * El correo y la contraseña salen del entorno: los valores de aquí son un
 * marcador para que una instalación recién clonada arranque. En producción se
 * definen LOTEA_OPERADOR_EMAIL y LOTEA_OPERADOR_PASSWORD antes del primer seed
 * y no vuelven a hacer falta.
 */
class OperadorSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('LOTEA_OPERADOR_EMAIL', 'admin@lotea.gt')],
            [
                'name' => env('LOTEA_OPERADOR_NAME', 'Administrador'),
                'password' => env('LOTEA_OPERADOR_PASSWORD', 'password'),
                'activo' => true,
                'es_operador' => true,
            ],
        );
    }
}
