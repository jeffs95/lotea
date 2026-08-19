<?php

namespace Tests\Feature;

use App\Actions\AbrirTicket;
use App\Actions\CrearEmpresa;
use App\Filament\Central\Pages\DiagnosticoDePermisos;
use App\Models\Empresa;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SoporteTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        Tenancy::usar($this->empresa);

        foreach (['ViewAny:Unidad', 'View:Unidad', 'Create:Unidad', 'ver_costos_unidad'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $this->vendedor = User::factory()->create(['name' => 'Carlos Ramírez']);
        $this->vendedor->empresas()->attach($this->empresa);

        Tenancy::comoEmpresa($this->empresa, function () {
            Role::findOrCreate('vendedor', 'web')->syncPermissions(['ViewAny:Unidad', 'View:Unidad']);
            $this->vendedor->assignRole('vendedor');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_un_reporte_guarda_el_contexto_solo(): void
    {
        $ticket = app(AbrirTicket::class)->ejecutar($this->vendedor, [
            'asunto' => 'No puedo agregar un vehículo',
            'mensaje' => 'No me aparece el botón de nueva unidad.',
            'pantalla' => 'Unidades',
        ]);

        $this->assertSame('T-0001', $ticket->numero);
        $this->assertSame($this->empresa->id, $ticket->empresa_id);
        $this->assertSame('abierto', $ticket->estado);
        $this->assertSame('vendedor', $ticket->contexto['rol']);
        $this->assertSame('Unidades', $ticket->contexto['pantalla']);
    }

    public function test_los_reportes_de_una_empresa_no_se_ven_desde_otra(): void
    {
        app(AbrirTicket::class)->ejecutar($this->vendedor, [
            'asunto' => 'Algo pasa',
            'mensaje' => 'Algo pasa.',
        ]);

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Zona 11']));

        $this->assertSame(0, Ticket::count());
    }

    public function test_el_operador_ve_los_tickets_de_todos_los_clientes(): void
    {
        app(AbrirTicket::class)->ejecutar($this->vendedor, [
            'asunto' => 'No puedo agregar un vehículo',
            'mensaje' => 'No me aparece el botón.',
        ]);

        $operador = User::factory()->create(['es_operador' => true]);

        $this->actingAs($operador)
            ->get('/central/soporte')
            ->assertOk()
            ->assertSee('No puedo agregar un vehículo')
            ->assertSee('Autos del Valle');
    }

    public function test_el_diagnostico_muestra_lo_que_el_usuario_puede_y_no_puede(): void
    {
        $pagina = new DiagnosticoDePermisos;
        $pagina->empresaId = $this->empresa->id;
        $pagina->usuarioId = $this->vendedor->id;

        $unidades = collect($pagina->getMatriz()->get('Unidades'));

        $this->assertTrue($unidades->firstWhere('accion', 'Ver el listado')['concedido']);
        $this->assertFalse($unidades->firstWhere('accion', 'Crear')['concedido']);

        $dinero = collect($pagina->getMatriz()->get('Dinero'));
        $this->assertFalse($dinero->firstWhere('accion', 'Ver costos y márgenes')['concedido']);
    }

    public function test_el_resumen_para_whatsapp_dice_lo_esencial(): void
    {
        $pagina = new DiagnosticoDePermisos;
        $pagina->empresaId = $this->empresa->id;
        $pagina->usuarioId = $this->vendedor->id;

        $resumen = $pagina->getResumenParaCopiar();

        $this->assertStringContainsString('Carlos Ramírez', $resumen);
        $this->assertStringContainsString('Rol: vendedor', $resumen);
        $this->assertStringContainsString('Unidades: Ver el listado, Ver el detalle', $resumen);
        $this->assertStringContainsString('Sin acceso a:', $resumen);
        $this->assertStringContainsString('Dinero', $resumen);
    }

    public function test_un_usuario_sin_rol_se_marca_como_tal(): void
    {
        $huerfano = User::factory()->create(['name' => 'Sin Rol']);
        $huerfano->empresas()->attach($this->empresa);

        $pagina = new DiagnosticoDePermisos;
        $pagina->empresaId = $this->empresa->id;
        $pagina->usuarioId = $huerfano->id;

        $this->assertTrue($pagina->getRoles()->isEmpty());
        $this->assertStringContainsString('SIN ROL ASIGNADO', $pagina->getResumenParaCopiar());
    }

    /**
     * Que la pantalla cargue no basta: los botones de Filament los autoriza el
     * Gate, no el recurso. Este test existe porque el botón de reportar no
     * aparecía aunque el listado sí cargaba.
     */
    public function test_cualquier_usuario_puede_reportar_un_problema(): void
    {
        $this->assertTrue($this->vendedor->can('viewAny', Ticket::class));
        $this->assertTrue($this->vendedor->can('create', Ticket::class));

        $this->actingAs($this->vendedor)
            ->get("/app/{$this->empresa->slug}/soporte")
            ->assertOk()
            ->assertSee('Reportar un problema');
    }

    public function test_un_usuario_no_ve_los_reportes_de_un_companero(): void
    {
        $ajeno = User::factory()->create(['name' => 'Otro Empleado']);
        $ajeno->empresas()->attach($this->empresa);

        $ticket = app(AbrirTicket::class)->ejecutar($ajeno, [
            'asunto' => 'Reporte de alguien más',
            'mensaje' => 'No es asunto del vendedor.',
        ]);

        $this->assertFalse($this->vendedor->can('view', $ticket));

        $this->actingAs($this->vendedor)
            ->get("/app/{$this->empresa->slug}/soporte")
            ->assertOk()
            ->assertDontSee('Reporte de alguien más');
    }

    /** Responder y cerrar es cosa de Lotea, no del cliente. */
    public function test_el_cliente_no_puede_responder_su_propio_ticket(): void
    {
        $ticket = app(AbrirTicket::class)->ejecutar($this->vendedor, [
            'asunto' => 'Una duda',
            'mensaje' => 'Una duda.',
        ]);

        $operador = User::factory()->create(['es_operador' => true]);

        $this->assertFalse($this->vendedor->can('update', $ticket));
        $this->assertTrue($operador->can('update', $ticket));
    }

    public function test_responder_un_ticket_deja_constancia_de_quien_contesto(): void
    {
        $ticket = app(AbrirTicket::class)->ejecutar($this->vendedor, [
            'asunto' => 'No puedo agregar un vehículo',
            'mensaje' => 'No me aparece el botón.',
        ]);

        $operador = User::factory()->create(['es_operador' => true]);

        $ticket->update([
            'respuesta' => 'Le falta el permiso de crear unidades. Que el dueño se lo active en Roles.',
            'estado' => 'resuelto',
            'respondido_por' => $operador->id,
            'respondido_en' => now(),
        ]);

        $this->assertTrue($ticket->fresh()->estaResuelto());
        $this->assertSame($operador->id, $ticket->fresh()->respondido_por);
        $this->assertNull($ticket->fresh()->horas_esperando);
    }
}
