<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
use App\Models\Empresa;
use App\Models\Unidad;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Recorre el panel por HTTP, que es donde se rompen las conexiones entre
 * Filament y la tenancy: el modelo puede estar perfecto y la pantalla
 * mostrarle a un cliente los carros de otro.
 */
class PanelDeUnidadesTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);

        $this->usuario = User::factory()->create();
        $this->usuario->empresas()->attach($this->empresa);

        // shield:generate no corre en los tests, así que sembramos a mano los
        // permisos del recurso que estamos ejercitando.
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete'] as $accion) {
            Permission::findOrCreate("{$accion}:Unidad", 'web');
        }

        Tenancy::comoEmpresa($this->empresa, function () {
            Role::findByName('dueno', 'web')->syncPermissions(Permission::all());
            $this->usuario->assignRole('dueno');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function url(string $ruta = ''): string
    {
        return "/app/{$this->empresa->slug}/unidades{$ruta}";
    }

    public function test_el_listado_carga_y_muestra_las_unidades_de_la_empresa(): void
    {
        $unidad = Tenancy::comoEmpresa($this->empresa, fn () => Unidad::factory()->create([
            'stock_no' => 'PROPIA-1',
        ]));

        $this->actingAs($this->usuario)
            ->get($this->url())
            ->assertOk()
            ->assertSee($unidad->stock_no);
    }

    public function test_el_listado_no_muestra_unidades_de_otra_empresa(): void
    {
        $otra = (new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Zona 11']);

        Tenancy::comoEmpresa($otra, fn () => Unidad::factory()->create(['stock_no' => 'AJENA-9']));

        $this->actingAs($this->usuario)
            ->get($this->url())
            ->assertOk()
            ->assertDontSee('AJENA-9');
    }

    public function test_un_usuario_no_puede_entrar_al_panel_de_una_empresa_ajena(): void
    {
        $otra = (new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Zona 11']);

        // Filament responde 404, no 403: para este usuario esa empresa
        // sencillamente no existe.
        $this->actingAs($this->usuario)
            ->get("/app/{$otra->slug}/unidades")
            ->assertNotFound();
    }

    public function test_la_ficha_de_una_unidad_carga(): void
    {
        $unidad = Tenancy::comoEmpresa($this->empresa, fn () => Unidad::factory()->create([
            'estado' => EstadoUnidad::Recibida,
        ]));

        $this->actingAs($this->usuario)
            ->get($this->url("/{$unidad->id}/editar"))
            ->assertOk()
            ->assertSee($unidad->stock_no);
        // El historial es un relation manager que Livewire carga aparte; su
        // contenido se prueba en CicloDeVidaDeLaUnidadTest.
    }

    public function test_no_se_puede_abrir_la_ficha_de_una_unidad_ajena(): void
    {
        $otra = (new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Zona 11']);
        $ajena = Tenancy::comoEmpresa($otra, fn () => Unidad::factory()->create());

        $this->actingAs($this->usuario)
            ->get($this->url("/{$ajena->id}/editar"))
            ->assertNotFound();
    }
}
