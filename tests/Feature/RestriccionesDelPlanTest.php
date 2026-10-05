<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
use App\Filament\Resources\Sucursales\SucursalResource;
use App\Filament\Resources\Unidades\UnidadResource;
use App\Filament\Resources\Ventas\VentaResource;
use App\Models\Empresa;
use App\Models\Plan;
use App\Models\Sucursal;
use App\Models\Unidad;
use App\Models\User;
use App\Support\AlcanceDelPlan;
use App\Support\Tenancy;
use Database\Seeders\PermisosDeShieldSeeder;
use Database\Seeders\PermisosPropiosSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lo que cada concesionario puede usar según lo que paga.
 *
 * Los planes eran un precio y una lista bonita: los módulos se guardaban pero
 * solo la lectura con IA los miraba, y los topes de sucursales, usuarios y
 * unidades no los verificaba nadie. Un cliente del plan más barato tenía el
 * sistema entero, así que el plan de en medio no se sostenía solo y el
 * cuadro de precios era una promesa que el programa no cumplía.
 */
class RestriccionesDelPlanTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        (new PermisosDeShieldSeeder)->run();
        (new PermisosPropiosSeeder)->run();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        Tenancy::usar($this->empresa);
    }

    protected function conPlan(array $datos): Empresa
    {
        $this->empresa->update(['plan_id' => Plan::factory()->create($datos)->getKey()]);

        return $this->empresa->fresh();
    }

    protected function sucursal(string $codigo): Sucursal
    {
        return Sucursal::create([
            'codigo' => $codigo,
            'nombre' => "Sucursal {$codigo}",
        ]);
    }

    protected function entraElDueno(Empresa $empresa): void
    {
        $usuario = User::factory()->create();
        $usuario->empresas()->attach($empresa->getKey());
        Tenancy::comoEmpresa($empresa, fn () => $usuario->assignRole('dueno'));

        $this->actingAs($usuario->fresh());
        Filament::setTenant($empresa);
    }

    /** El plan sin el módulo de ventas no abre la pantalla de ventas. */
    public function test_un_plan_sin_el_modulo_no_abre_esa_pantalla(): void
    {
        $this->entraElDueno($this->conPlan(['modulos' => ['unidades', 'importacion', 'costeo', 'portal']]));

        $this->assertFalse(VentaResource::canAccess());
        $this->assertTrue(UnidadResource::canAccess());
    }

    public function test_el_plan_que_si_lo_trae_la_abre(): void
    {
        $this->entraElDueno($this->conPlan(['modulos' => ['unidades', 'ventas']]));

        $this->assertTrue(VentaResource::canAccess());
    }

    /**
     * Una empresa sin plan es un descuido de configuración, no un cliente que
     * dejó de pagar: dejarla encerrada fuera de su sistema hace más daño del
     * que evita.
     */
    public function test_sin_plan_no_se_le_cierra_nada(): void
    {
        $this->entraElDueno($this->empresa);

        $this->assertNull($this->empresa->fresh()->plan);
        $this->assertTrue(VentaResource::canAccess());
        $this->assertTrue(UnidadResource::canAccess());
    }

    /** Lo que cuesta dinero por uso sigue cerrado sin plan. */
    public function test_la_lectura_con_ia_si_se_cierra_sin_plan(): void
    {
        $this->assertFalse($this->empresa->tieneModulo('ia'));
        $this->assertFalse($this->empresa->puedeLeerConIa());
    }

    public function test_al_tope_de_sucursales_ya_no_se_agrega(): void
    {
        $empresa = $this->conPlan(['modulos' => ['unidades'], 'max_sucursales' => 2]);
        $this->entraElDueno($empresa);

        // CrearEmpresa ya deja una sucursal, así que falta una para el tope.
        $this->assertTrue(SucursalResource::canCreate());

        $this->sucursal('B');

        $this->assertSame(2, AlcanceDelPlan::cuantosTiene($empresa, 'sucursales'));
        $this->assertFalse(SucursalResource::canCreate());
        $this->assertStringContainsString('2 sucursales', SucursalResource::avisoDelTope());
    }

    /**
     * Y lo niega la política, no solo el recurso.
     *
     * Puesto solo en el recurso, el botón de «Nueva sucursal» seguía ahí: lo
     * dibuja una acción que pregunta a la política, no al recurso. Llevaba a
     * un formulario que no iba a guardar.
     */
    public function test_la_politica_tambien_niega_al_llegar_al_tope(): void
    {
        $empresa = $this->conPlan(['modulos' => ['unidades'], 'max_sucursales' => 1]);
        $this->entraElDueno($empresa);

        $this->assertFalse(auth()->user()->can('create', Sucursal::class));
        $this->assertTrue(auth()->user()->can('update', Sucursal::query()->first()),
            'El tope bloqueó también la edición de lo que ya existe.');
    }

    /** El plan sin tope no limita: es lo que se vende como «ilimitado». */
    public function test_el_plan_sin_tope_no_limita(): void
    {
        $empresa = $this->conPlan(['modulos' => ['unidades'], 'max_sucursales' => null]);
        $this->entraElDueno($empresa);

        foreach (['B', 'C', 'D', 'E', 'F'] as $codigo) {
            $this->sucursal($codigo);
        }

        $this->assertTrue(SucursalResource::canCreate());
        $this->assertNull(SucursalResource::avisoDelTope());
    }

    /**
     * El tope es de lo que se está administrando hoy, no del histórico: un
     * carro vendido ya no ocupa lugar en el patio, y si contara, el cliente
     * se quedaría sin poder dar de alta nada al cabo de unos meses.
     */
    public function test_las_vendidas_no_ocupan_lugar(): void
    {
        $empresa = $this->conPlan(['modulos' => ['unidades'], 'max_unidades_activas' => 2]);
        $this->entraElDueno($empresa);

        Unidad::factory()->count(2)->create(['estado' => EstadoUnidad::Publicada]);

        $this->assertFalse(UnidadResource::canCreate());

        Unidad::query()->first()->update(['estado' => EstadoUnidad::Vendida]);

        $this->assertSame(1, AlcanceDelPlan::cuantosTiene($empresa->fresh(), 'unidades'));
        $this->assertTrue(UnidadResource::canCreate());
    }

    public function test_el_aviso_dice_el_plan_y_el_numero(): void
    {
        $empresa = $this->conPlan([
            'nombre' => 'Básico',
            'modulos' => ['unidades'],
            'max_unidades_activas' => 1,
        ]);
        $this->entraElDueno($empresa);

        Unidad::factory()->create(['estado' => EstadoUnidad::Publicada]);

        $aviso = UnidadResource::avisoDelTope();

        $this->assertStringContainsString('Básico', $aviso);
        $this->assertStringContainsString('1 unidades', $aviso);
        $this->assertStringContainsString('subir de plan', $aviso);
    }
}
