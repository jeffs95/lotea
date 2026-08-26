<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\Empresa;
use App\Models\Rastro;
use App\Models\Role;
use App\Models\Unidad;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Lotea entrando al panel de un cliente para dar soporte.
 *
 * La cuenta de Lotea no pertenece a la empresa del concesionario —ni debe, o
 * contaría como usuario suyo— así que Filament respondía 404 y el botón «Abrir
 * su panel» del central no llevaba a ninguna parte.
 *
 * Lo que hay que cuidar aquí es que la puerta no quede abierta para nadie más.
 */
class SoporteAlClienteTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $cliente;

    protected User $operador;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Unidad', 'Venta', 'Cliente'] as $modulo) {
            foreach (['ViewAny', 'View', 'Create', 'Update'] as $accion) {
                Permission::findOrCreate("{$accion}:{$modulo}", 'web');
            }
        }

        $this->cliente = (new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Gómez']);

        $this->operador = User::factory()->create(['es_operador' => true]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function entrar(?Empresa $empresa = null)
    {
        return $this->actingAs($this->operador)
            ->get(route('soporte.entrar', ['empresa' => ($empresa ?? $this->cliente)->slug]));
    }

    /** El caso que estaba roto: el operador entra y ve el panel del cliente. */
    public function test_el_operador_entra_al_panel_del_cliente(): void
    {
        $this->entrar()->assertRedirect(
            route('filament.admin.pages.dashboard', ['tenant' => $this->cliente->slug])
        );

        $this->actingAs($this->operador)
            ->get("/app/{$this->cliente->slug}")
            ->assertSuccessful();
    }

    /** Sin la sesión de soporte, ese panel sigue siendo 404 para él. */
    public function test_sin_entrar_a_soporte_el_panel_del_cliente_no_existe(): void
    {
        $this->actingAs($this->operador)
            ->get("/app/{$this->cliente->slug}")
            ->assertNotFound();
    }

    /**
     * Y aquí está lo importante: esto es solo para Lotea. Un dueño de
     * concesionario no puede usar la misma puerta para entrar a otro.
     */
    public function test_un_cliente_no_puede_entrar_al_panel_de_otro(): void
    {
        $otra = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Norte']);

        $dueno = User::factory()->create(['es_operador' => false]);
        $dueno->empresas()->attach($otra);

        Tenancy::comoEmpresa($otra, fn () => $dueno->assignRole('dueno'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($dueno)
            ->get(route('soporte.entrar', ['empresa' => $this->cliente->slug]))
            ->assertForbidden();

        $this->actingAs($dueno)
            ->get("/app/{$this->cliente->slug}")
            ->assertNotFound();
    }

    public function test_un_visitante_sin_sesion_no_entra(): void
    {
        $this->get(route('soporte.entrar', ['empresa' => $this->cliente->slug]))
            ->assertRedirect();
    }

    /** A un concesionario suspendido no se entra: es la palanca de cobro. */
    public function test_no_se_entra_a_un_concesionario_suspendido(): void
    {
        $this->cliente->update(['suspendida_en' => now(), 'motivo_suspension' => 'No pagó']);

        $this->entrar()->assertNotFound();
    }

    /** Si a alguien se le quita la bandera de operador, su sesión deja de valer. */
    public function test_quitarle_la_bandera_corta_el_acceso_en_el_acto(): void
    {
        $this->entrar();

        $this->operador->update(['es_operador' => false]);

        $this->actingAs($this->operador)
            ->get("/app/{$this->cliente->slug}")
            ->assertNotFound();
    }

    public function test_al_salir_se_cierra_el_acceso(): void
    {
        $this->entrar();

        $this->actingAs($this->operador)
            ->get(route('soporte.salir'))
            ->assertRedirect(route('filament.central.pages.dashboard'));

        $this->actingAs($this->operador)
            ->get("/app/{$this->cliente->slug}")
            ->assertNotFound();
    }

    /** Es de una empresa a la vez: entrar a otra cierra la anterior. */
    public function test_entrar_a_otro_cliente_cierra_el_anterior(): void
    {
        $otra = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Norte']);

        $this->entrar();
        $this->entrar($otra);

        $this->actingAs($this->operador)->get("/app/{$otra->slug}")->assertSuccessful();
        $this->actingAs($this->operador)->get("/app/{$this->cliente->slug}")->assertNotFound();
    }

    // ── Lo que puede hacer dentro ───────────────────────────────────────────

    /**
     * Sin permisos no serviría de nada: entraría al panel y no podría abrir una
     * sola pantalla, que es igual de inútil que el 404 de antes.
     */
    public function test_dentro_del_soporte_puede_abrir_las_pantallas(): void
    {
        $this->entrar();

        Tenancy::comoEmpresa($this->cliente, fn () => Unidad::factory()->create());

        $this->actingAs($this->operador)
            ->get("/app/{$this->cliente->slug}/unidades")
            ->assertSuccessful();
    }

    /** Y esos permisos no se le quedan pegados fuera del soporte. */
    public function test_los_permisos_del_soporte_no_sobreviven_a_la_salida(): void
    {
        $this->entrar();
        $this->actingAs($this->operador)->get(route('soporte.salir'));

        $this->actingAs($this->operador)
            ->get("/app/{$this->cliente->slug}/unidades")
            ->assertNotFound();
    }

    // ── El registro ─────────────────────────────────────────────────────────

    /**
     * Si un cliente reclama que le cambiaron algo, esto es lo que permite
     * responder. Sin registro, su palabra contra la nuestra.
     */
    public function test_la_entrada_queda_anotada_en_el_historial_del_cliente(): void
    {
        $this->entrar();

        $rastro = Tenancy::comoEmpresa(
            $this->cliente,
            fn () => Rastro::where('log_name', 'soporte')->latest('id')->first(),
        );

        $this->assertNotNull($rastro, 'No quedó anotada la entrada.');
        $this->assertSame('Lotea entró a dar soporte', $rastro->description);
        $this->assertSame($this->operador->getKey(), $rastro->causer_id);
        $this->assertSame($this->cliente->getKey(), $rastro->empresa_id);
    }

    public function test_la_salida_tambien_queda_anotada(): void
    {
        $this->entrar();
        $this->actingAs($this->operador)->get(route('soporte.salir'));

        $anotaciones = Tenancy::comoEmpresa(
            $this->cliente,
            fn () => Rastro::where('log_name', 'soporte')->pluck('description')->all(),
        );

        $this->assertContains('Lotea entró a dar soporte', $anotaciones);
        $this->assertContains('Lotea salió del soporte', $anotaciones);
    }

    /** El aviso tiene que estar a la vista: el riesgo es olvidarse. */
    public function test_el_panel_avisa_que_se_esta_dando_soporte(): void
    {
        $this->entrar();

        $this->actingAs($this->operador)
            ->get("/app/{$this->cliente->slug}")
            ->assertSee('para dar soporte')
            ->assertSee('Salir del soporte');
    }

    public function test_el_cliente_no_ve_esa_barra_en_su_propio_panel(): void
    {
        $dueno = User::factory()->create();
        $dueno->empresas()->attach($this->cliente);

        Tenancy::comoEmpresa($this->cliente, function () use ($dueno) {
            Role::findByName('dueno', 'web')->syncPermissions(Permission::all());
            $dueno->assignRole('dueno');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($dueno)
            ->get("/app/{$this->cliente->slug}")
            ->assertSuccessful()
            ->assertDontSee('Salir del soporte');
    }

    /** No se cuenta como usuario del cliente: no ensucia su lista ni su plan. */
    public function test_el_operador_no_queda_como_usuario_del_cliente(): void
    {
        $this->entrar();

        $this->assertFalse(
            $this->cliente->usuarios()->whereKey($this->operador->getKey())->exists(),
            'El operador quedó registrado como usuario del concesionario.',
        );
    }
}
