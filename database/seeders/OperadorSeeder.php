<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/** La cuenta con la que Lotea entra a su propio panel. */
class OperadorSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'operador@lotea.gt'],
            [
                'name' => 'Jeferson (Lotea)',
                'password' => 'password',
                'activo' => true,
                'es_operador' => true,
            ],
        );
    }
}
