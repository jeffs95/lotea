<?php

namespace Database\Seeders;

use App\Actions\AbrirTicket;
use App\Models\Empresa;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

/** Un caso de soporte real para que la bandeja no arranque vacía. */
class SoporteDemoSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::firstWhere('slug', 'autos-del-valle');
        $vendedor = User::firstWhere('email', 'vendedor@lotea.test');

        if (! $empresa || ! $vendedor) {
            return;
        }

        Tenancy::comoEmpresa($empresa, function () use ($vendedor) {
            if (Ticket::count() > 0) {
                return;
            }

            app(AbrirTicket::class)->ejecutar($vendedor, [
                'asunto' => 'No puedo agregar un vehículo',
                'mensaje' => 'Entro a Unidades y no me aparece el botón para crear una nueva. Ayer sí me aparecía.',
                'pantalla' => 'Unidades',
            ]);
        });
    }
}
