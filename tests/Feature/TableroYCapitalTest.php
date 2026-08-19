<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
use App\Filament\Widgets\CapitalEnPatio;
use App\Models\Empresa;
use App\Models\Role;
use App\Models\Unidad;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TableroYCapitalTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        Tenancy::usar($this->empresa);
    }

    protected function usuarioCon(array $permisos): User
    {
        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $usuario = User::factory()->create();
        $usuario->empresas()->attach($this->empresa);

        Tenancy::comoEmpresa($this->empresa, function () use ($usuario, $permisos) {
            $rol = Role::findOrCreate('temporal_'.$usuario->id, 'web');
            $rol->syncPermissions($permisos);
            $usuario->assignRole($rol);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $usuario;
    }

    public function test_el_capital_en_patio_no_cuenta_lo_que_ya_se_vendio(): void
    {
        Unidad::factory()->count(2)->create([
            'estado' => EstadoUnidad::EnTaller,
            'costo_total' => 50000,
        ]);

        Unidad::factory()->create([
            'estado' => EstadoUnidad::Entregada,
            'costo_total' => 90000,
        ]);

        $this->assertEquals(100000, Unidad::enInventario()->sum('costo_total'));
    }

    public function test_el_tablero_muestra_los_montos_a_quien_puede_ver_costos(): void
    {
        Unidad::factory()->create([
            'estado' => EstadoUnidad::EnTaller,
            'costo_total' => 84729,
        ]);

        $usuario = $this->usuarioCon(['ver_costos_unidad', 'View:TableroUnidades']);

        $this->actingAs($usuario)
            ->get("/app/{$this->empresa->slug}/tablero")
            ->assertOk()
            ->assertSee('Capital inmovilizado en el patio')
            ->assertSee('84,729.00');
    }

    /** El requisito de negocio más importante del sistema. */
    public function test_un_vendedor_no_ve_el_capital_en_el_tablero(): void
    {
        Unidad::factory()->create([
            'estado' => EstadoUnidad::EnTaller,
            'costo_total' => 84729,
        ]);

        $vendedor = $this->usuarioCon(['View:TableroUnidades']);

        $this->actingAs($vendedor)
            ->get("/app/{$this->empresa->slug}/tablero")
            ->assertOk()
            ->assertDontSee('Capital inmovilizado en el patio')
            ->assertDontSee('84,729.00');
    }

    public function test_el_widget_de_capital_calcula_bien_lo_dormido(): void
    {
        // Comprada hace 200 días: capital dormido.
        Unidad::factory()->create([
            'estado' => EstadoUnidad::EnTaller,
            'costo_total' => 70000,
            'fecha_compra' => now()->subDays(200),
        ]);

        // Comprada hace 20 días: sana.
        Unidad::factory()->create([
            'estado' => EstadoUnidad::EnTaller,
            'costo_total' => 30000,
            'fecha_compra' => now()->subDays(20),
        ]);

        $usuario = $this->usuarioCon(['ver_costos_unidad']);

        Livewire::actingAs($usuario)
            ->test(CapitalEnPatio::class)
            ->assertSee('Q 100,000.00')      // capital total en patio
            ->assertSee('Q 70,000.00')       // solo la estancada
            ->assertSee('1 unidad con más de 120 días');
    }
}
