<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * La cuenta con la que Lotea entra a su propio panel.
 *
 * La contraseña sale del entorno para no dejarla escrita en el repositorio: en
 * producción se define LOTEA_OPERADOR_PASSWORD antes del primer seed y no
 * vuelve a hacer falta.
 */
class OperadorSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('LOTEA_OPERADOR_EMAIL', 'jeffersonjuarez0101@gmail.com')],
            [
                'name' => env('LOTEA_OPERADOR_NAME', 'Jefferson Juárez'),
                'password' => env('LOTEA_OPERADOR_PASSWORD', 'password'),
                'activo' => true,
                'es_operador' => true,
            ],
        );
    }
}
