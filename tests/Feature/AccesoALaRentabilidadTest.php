<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Actions\RegistrarCosto;
use App\Models\CategoriaCosto;
use App\Models\Empresa;
use App\Models\Role;
use App\Models\Unidad;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * La ficha de rentabilidad enseña el costo y el margen de una unidad. Es
 * exactamente lo que un vendedor no debe ver: si sabe que el carro costó
 * Q86,000, negocia contra su propio patrón.
 */
class AccesoALaRentabilidadTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected Unidad $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        Tenancy::usar($this->empresa);

        $this->unidad = Unidad::factory()->create(['precio_lista' => 148000]);

        app(RegistrarCosto::class)->ejecutar($this->unidad, [
            'categoria_costo_id' => CategoriaCosto::where('codigo', 'precio_compra')->value('id'),
            'monto' => 4200,
            'moneda' => 'USD',
            'tipo_cambio' => 7.70,
        ]);
    }

    protected function usuarioCon(array $permisos): User
    {
        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $usuario = User::factory()->create();
        $usuario->empresas()->attach($this->empresa);

        Tenancy::comoEmpresa($this->empresa, function () use ($usuario, $permisos) {
            $rol = Role::findOrCreate('rol_'.$usuario->id, 'web');
            $rol->syncPermissions($permisos);
            $usuario->assignRole($rol);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $usuario;
    }

    protected function url(): string
    {
        return "/app/{$this->empresa->slug}/unidades/{$this->unidad->id}/rentabilidad";
    }

    public function test_el_dueno_ve_el_costo_y_el_margen(): void
    {
        $dueno = $this->usuarioCon(['ver_costos_unidad', 'ViewAny:Unidad', 'View:Unidad', 'Update:Unidad']);

        $this->actingAs($dueno)
            ->get($this->url())
            ->assertOk()
            ->assertSee('32,340.00')       // el martillo convertido a quetzales
            ->assertSee('Utilidad');
    }

    public function test_un_vendedor_no_puede_abrir_la_ficha_de_rentabilidad(): void
    {
        $vendedor = $this->usuarioCon(['ViewAny:Unidad', 'View:Unidad', 'Update:Unidad']);

        $this->actingAs($vendedor)
            ->get($this->url())
            ->assertForbidden();
    }

    public function test_no_se_puede_ver_la_rentabilidad_de_una_unidad_de_otra_empresa(): void
    {
        $otra = (new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Zona 11']);
        $ajena = Tenancy::comoEmpresa($otra, fn () => Unidad::factory()->create());

        $dueno = $this->usuarioCon(['ver_costos_unidad', 'ViewAny:Unidad', 'View:Unidad', 'Update:Unidad']);

        $this->actingAs($dueno)
            ->get("/app/{$this->empresa->slug}/unidades/{$ajena->id}/rentabilidad")
            ->assertNotFound();
    }
}
