<?php

namespace Database\Seeders;

use App\Actions\CrearEmpresa;
use App\Models\Empresa;
use App\Models\Unidad;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([CatalogosSeeder::class, PermisosPropiosSeeder::class, PlanesSeeder::class, OperadorSeeder::class]);

        $empresa = Empresa::firstWhere('slug', 'autos-del-valle') ?? (new CrearEmpresa)->ejecutar([
            'plan_id' => \App\Models\Plan::firstWhere('slug', 'pro')?->id,
            'nombre' => 'Autos del Valle, S.A.',
            'nombre_comercial' => 'Autos del Valle',
            'nit' => '1234567-8',
            'slug' => 'autos-del-valle',
            'telefono' => '2222-3333',
            'email' => 'ventas@autosdelvalle.gt',
            'direccion' => 'Calzada Roosevelt 12-34, zona 11, Guatemala',
        ], 'Patio Roosevelt');

        $dueno = User::firstOrCreate(
            ['email' => 'dueno@lotea.test'],
            ['name' => 'Jeferson (dueño)', 'password' => 'password', 'activo' => true],
        );

        $dueno->empresas()->syncWithoutDetaching([$empresa->id]);

        Tenancy::comoEmpresa($empresa, function () use ($dueno) {
            $dueno->assignRole('dueno');

            if (Unidad::count() === 0) {
                $this->call([UnidadesDemoSeeder::class, CostosDemoSeeder::class, VentasDemoSeeder::class, EmpleadosDemoSeeder::class, CajasDemoSeeder::class, TallerDemoSeeder::class]);
            }
        });

        // Fuera del contexto de la primera empresa: da de alta a las demás.
        $this->call([ConcesionariosDemoSeeder::class, SoporteDemoSeeder::class]);
    }
}
