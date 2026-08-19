<?php

namespace Database\Seeders;

use App\Actions\CrearEmpresa;
use App\Models\Empresa;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogosSeeder::class);

        $empresa = Empresa::firstWhere('slug', 'autos-del-valle') ?? (new CrearEmpresa)->ejecutar([
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

        Tenancy::comoEmpresa($empresa, function () use ($dueno, $empresa) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($empresa->id);
            $dueno->assignRole('dueno');
        });
    }
}
