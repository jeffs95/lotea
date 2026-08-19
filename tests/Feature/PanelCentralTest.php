<?php

namespace Tests\Feature;

use App\Actions\AltaDeConcesionario;
use App\Actions\CrearEmpresa;
use App\Actions\GenerarCobrosDelMes;
use App\Actions\SuspenderConcesionario;
use App\Models\CategoriaCosto;
use App\Models\Cobro;
use App\Models\Empresa;
use App\Models\Plan;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\Tenancy;
use App\Filament\Central\Resources\Concesionarios\Pages\CreateConcesionario;
use Database\Seeders\PlanesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El panel central es de Lotea, no de los concesionarios. Que un cliente no
 * pueda asomarse aquí es lo más importante de este módulo: desde adentro se
 * ven los datos, los precios y los cobros de toda la competencia.
 */
class PanelCentralTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanesSeeder::class);
    }

    protected function operador(): User
    {
        return User::factory()->create(['es_operador' => true]);
    }

    protected function clienteDe(Empresa $empresa): User
    {
        $usuario = User::factory()->create(['es_operador' => false]);
        $usuario->empresas()->attach($empresa);

        return $usuario;
    }

    public function test_el_operador_entra_al_panel_central(): void
    {
        $this->actingAs($this->operador())
            ->get('/central')
            ->assertOk();
    }

    /** La prueba que sostiene todo el negocio. */
    public function test_el_dueno_de_un_concesionario_no_entra_al_panel_central(): void
    {
        $empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);

        $this->actingAs($this->clienteDe($empresa))
            ->get('/central')
            ->assertForbidden();
    }

    public function test_un_usuario_desactivado_tampoco_entra_aunque_sea_operador(): void
    {
        $operador = User::factory()->create(['es_operador' => true, 'activo' => false]);

        $this->actingAs($operador)->get('/central')->assertForbidden();
    }

    public function test_el_operador_ve_los_concesionarios_de_todos(): void
    {
        (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        (new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Zona 11']);

        $this->actingAs($this->operador())
            ->get('/central/concesionarios')
            ->assertOk()
            ->assertSee('Autos del Valle')
            ->assertSee('Importadora Zona 11');
    }

    public function test_el_panel_central_no_arrastra_el_contexto_de_una_empresa(): void
    {
        $empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);

        Tenancy::usar($empresa);

        $this->actingAs($this->operador())->get('/central')->assertOk();

        $this->assertFalse(Tenancy::hayEmpresa());
    }

    public function test_dar_de_alta_deja_al_concesionario_listo_para_trabajar(): void
    {
        $plan = Plan::firstWhere('slug', 'pro');

        $empresa = app(AltaDeConcesionario::class)->ejecutar(
            ['nombre' => 'Importadora del Sur, S.A.', 'nombre_comercial' => 'Importadora del Sur', 'sucursal_principal' => 'Patio Xela'],
            ['name' => 'Ana Pérez', 'email' => 'ana@importadoradelsur.gt', 'password' => 'secreto123'],
            $plan,
        );

        $dueno = User::firstWhere('email', 'ana@importadoradelsur.gt');

        $this->assertNotNull($dueno);
        $this->assertTrue($dueno->empresas->contains($empresa));
        $this->assertSame($plan->id, $empresa->plan_id);

        Tenancy::usar($empresa);

        $this->assertSame(1, Sucursal::where('nombre', 'Patio Xela')->count());
        $this->assertSame(count(CrearEmpresa::CATEGORIAS_BASE), CategoriaCosto::count());
        $this->assertTrue($dueno->fresh()->hasRole('dueno'));
        $this->assertSame(1, Cobro::where('empresa_id', $empresa->id)->count());
    }

    /**
     * El alta desde la pantalla, no solo desde la acción: es donde se cuelan
     * los campos del formulario que no son columnas del modelo.
     */
    public function test_el_alta_funciona_desde_el_formulario_del_panel(): void
    {
        $this->actingAs($this->operador());

        // Sin esto, Filament resuelve las rutas contra el panel por defecto.
        Filament::setCurrentPanel('central');

        Livewire::test(CreateConcesionario::class)
            ->fillForm([
                'nombre' => 'Importadora del Sur, S.A.',
                'nombre_comercial' => 'Importadora del Sur',
                'slug' => 'importadora-del-sur',
                'nit' => '9988776-5',
                'plan_id' => Plan::firstWhere('slug', 'pro')->id,
                'activa' => true,
                'dueno_nombre' => 'Ana Pérez',
                'dueno_email' => 'ana@importadoradelsur.gt',
                'dueno_password' => 'secreto123',
                'sucursal_principal' => 'Patio Xela',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $empresa = Empresa::firstWhere('slug', 'importadora-del-sur');

        $this->assertNotNull($empresa);

        Tenancy::usar($empresa);

        $this->assertSame('Patio Xela', Sucursal::first()->nombre);
        $this->assertNotNull(User::firstWhere('email', 'ana@importadoradelsur.gt'));
        $this->assertSame(1, Cobro::where('empresa_id', $empresa->id)->count());
    }

    public function test_el_dueno_recien_dado_de_alta_puede_entrar_a_su_panel(): void
    {
        $empresa = app(AltaDeConcesionario::class)->ejecutar(
            ['nombre' => 'Importadora del Sur'],
            ['name' => 'Ana Pérez', 'email' => 'ana@importadoradelsur.gt', 'password' => 'secreto123'],
            Plan::firstWhere('slug', 'basico'),
        );

        $dueno = User::firstWhere('email', 'ana@importadoradelsur.gt');

        $this->actingAs($dueno)
            ->get("/app/{$empresa->slug}")
            ->assertOk();
    }

    public function test_suspender_corta_el_acceso_sin_borrar_nada(): void
    {
        $empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        $accion = app(SuspenderConcesionario::class);

        $accion->suspender($empresa, 'Mensualidad de agosto sin pagar');

        $this->assertTrue($empresa->fresh()->estaSuspendida());
        $this->assertFalse($empresa->fresh()->puedeOperar());
        $this->assertSame('suspendida', $empresa->fresh()->estado_suscripcion);
        $this->assertSame('Mensualidad de agosto sin pagar', $empresa->fresh()->motivo_suspension);

        Tenancy::usar($empresa->fresh());
        $this->assertSame(count(CrearEmpresa::CATEGORIAS_BASE), CategoriaCosto::count());

        $accion->reactivar($empresa);

        $this->assertTrue($empresa->fresh()->puedeOperar());
        $this->assertNull($empresa->fresh()->motivo_suspension);
    }

    /** Si la suspensión no corta el acceso, no sirve para cobrar. */
    public function test_un_suspendido_no_puede_entrar_a_su_panel(): void
    {
        $empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        $usuario = $this->clienteDe($empresa);

        $this->actingAs($usuario)->get("/app/{$empresa->slug}")->assertOk();

        app(SuspenderConcesionario::class)->suspender($empresa, 'No paga');

        $this->actingAs($usuario)->get("/app/{$empresa->slug}")->assertNotFound();
        $this->assertFalse($usuario->canAccessTenant($empresa->fresh()));
        $this->assertCount(0, $usuario->getTenants(\Filament\Facades\Filament::getPanel('admin')));
    }

    public function test_el_portal_publico_de_un_suspendido_no_responde(): void
    {
        $empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);

        $this->get("/v/{$empresa->slug}/vehiculos")->assertOk();

        app(SuspenderConcesionario::class)->suspender($empresa, 'No paga');

        $this->get("/v/{$empresa->slug}/vehiculos")->assertNotFound();
    }

    public function test_reactivar_devuelve_el_acceso(): void
    {
        $empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        $usuario = $this->clienteDe($empresa);
        $accion = app(SuspenderConcesionario::class);

        $accion->suspender($empresa, 'No paga');
        $accion->reactivar($empresa);

        $this->actingAs($usuario)->get("/app/{$empresa->slug}")->assertOk();
        $this->get("/v/{$empresa->slug}/vehiculos")->assertOk();
    }

    public function test_no_se_puede_suspender_sin_motivo(): void
    {
        $empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);

        $this->expectException(DomainException::class);

        app(SuspenderConcesionario::class)->suspender($empresa, '  ');
    }

    public function test_emitir_mensualidades_dos_veces_no_duplica_cobros(): void
    {
        app(AltaDeConcesionario::class)->ejecutar(
            ['nombre' => 'Autos del Valle'],
            ['name' => 'Jefe', 'email' => 'jefe@adv.gt', 'password' => 'secreto123'],
            Plan::firstWhere('slug', 'pro'),
        );

        $accion = app(GenerarCobrosDelMes::class);
        $accion->ejecutar();
        $accion->ejecutar();

        $this->assertSame(1, Cobro::where('periodo', now()->format('Y-m'))->count());
    }

    public function test_a_un_suspendido_no_se_le_sigue_facturando(): void
    {
        $empresa = app(AltaDeConcesionario::class)->ejecutar(
            ['nombre' => 'Autos del Valle'],
            ['name' => 'Jefe', 'email' => 'jefe@adv.gt', 'password' => 'secreto123'],
            Plan::firstWhere('slug', 'pro'),
        );

        Cobro::query()->delete();

        app(SuspenderConcesionario::class)->suspender($empresa, 'No paga');
        app(GenerarCobrosDelMes::class)->ejecutar();

        $this->assertSame(0, Cobro::count());
    }

    public function test_el_ingreso_mensual_no_cuenta_a_los_suspendidos(): void
    {
        $pro = Plan::firstWhere('slug', 'pro');

        $alDia = (new CrearEmpresa)->ejecutar(['nombre' => 'Al día']);
        $alDia->update(['plan_id' => $pro->id]);

        $cortado = (new CrearEmpresa)->ejecutar(['nombre' => 'Cortado']);
        $cortado->update(['plan_id' => $pro->id]);
        app(SuspenderConcesionario::class)->suspender($cortado, 'No paga');

        $this->assertEquals($pro->precio_mensual, $alDia->fresh()->mensualidad);
        $this->assertEquals(0, $cortado->fresh()->mensualidad);
    }
}
