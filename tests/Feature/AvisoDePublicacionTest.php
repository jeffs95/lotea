<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
use App\Filament\Resources\Unidades\Pages\EditUnidad;
use App\Models\Empresa;
use App\Models\Role;
use App\Models\Unidad;
use App\Models\User;
use App\Support\RequisitosDelPortal;
use App\Support\Tenancy;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Cuando una unidad no va a salir en el portal, hay que decirlo.
 *
 * El modelo apaga «Publicado» solo si falta el precio o una foto, y eso pasaba
 * en silencio: el usuario marcaba el interruptor, guardaba, la unidad no
 * aparecía y no había forma de saber por qué. Parecía un bug del sistema.
 */
class AvisoDePublicacionTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);

        $this->usuario = User::factory()->create();
        $this->usuario->empresas()->attach($this->empresa);

        foreach (['ViewAny', 'View', 'Create', 'Update'] as $accion) {
            Permission::findOrCreate("{$accion}:Unidad", 'web');
        }

        // El rol dueño nace con los permisos que existan en ese momento, y
        // estos se acaban de crear: hay que volver a sincronizarlo.
        Tenancy::comoEmpresa($this->empresa, function () {
            Role::findByName('dueno', 'web')->syncPermissions(Permission::all());
            $this->usuario->assignRole('dueno');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->usuario);
        Tenancy::usar($this->empresa);
        Filament::setTenant($this->empresa);
    }

    protected function editar(Unidad $unidad, array $datos): void
    {
        Livewire::test(EditUnidad::class, ['record' => $unidad->getRouteKey()])
            ->fillForm($datos)
            ->call('save');
    }

    public function test_avisa_que_no_se_publico_y_dice_que_falto(): void
    {
        $unidad = Unidad::factory()->create([
            'estado' => EstadoUnidad::Recibida,
            'precio_lista' => null,
        ]);

        $this->editar($unidad, ['publicado' => true]);

        $this->assertFalse($unidad->refresh()->publicado);

        Notification::assertNotified('Se guardó, pero no se publicó');
    }

    /** Con precio pero sin foto, el aviso tiene que nombrar la foto. */
    public function test_el_aviso_nombra_lo_que_falta(): void
    {
        $unidad = Unidad::factory()->create([
            'estado' => EstadoUnidad::Recibida,
            'precio_lista' => 95000,
        ]);

        $this->editar($unidad, ['publicado' => true]);

        $faltan = RequisitosDelPortal::trabas($unidad->refresh()->precio_lista, $unidad->tieneAlgunaFoto());

        $this->assertSame(['al menos una foto'], $faltan);
        $this->assertFalse($unidad->publicado);
    }

    /**
     * El otro caso que confunde: la unidad sí queda publicada, pero el estado
     * todavía no la deja a la venta.
     */
    public function test_avisa_cuando_el_estado_no_deja_verla_todavia(): void
    {
        $unidad = Unidad::factory()->publicada()->create([
            'estado' => EstadoUnidad::Comprada,
            'precio_lista' => 95000,
        ]);

        $this->editar($unidad, ['publicado' => true]);

        $this->assertTrue($unidad->refresh()->publicado);

        Notification::assertNotified('Quedó publicada, pero todavía no se ve');
    }

    public function test_no_molesta_cuando_todo_esta_bien(): void
    {
        $unidad = Unidad::factory()->publicada()->create([
            'estado' => EstadoUnidad::Recibida,
            'precio_lista' => 95000,
        ]);

        $this->editar($unidad, ['publicado' => true]);

        $this->assertTrue($unidad->refresh()->publicado);

        Notification::assertNotNotified('Se guardó, pero no se publicó');
        Notification::assertNotNotified('Quedó publicada, pero todavía no se ve');
    }

    /** Quien no pidió publicar no tiene por qué recibir avisos del portal. */
    public function test_guardar_sin_marcar_publicado_no_avisa_nada(): void
    {
        $unidad = Unidad::factory()->create([
            'estado' => EstadoUnidad::Comprada,
            'precio_lista' => null,
        ]);

        $this->editar($unidad, ['ubicacion' => 'Fila 3']);

        Notification::assertNotNotified('Se guardó, pero no se publicó');
    }

    public function test_el_formulario_lista_los_requisitos_antes_de_guardar(): void
    {
        $unidad = Unidad::factory()->create([
            'estado' => EstadoUnidad::Comprada,
            'precio_lista' => null,
        ]);

        Livewire::test(EditUnidad::class, ['record' => $unidad->getRouteKey()])
            ->assertSee('Así no va a aparecer en el portal')
            ->assertSee('el precio de lista')
            ->assertSee('al menos una foto');
    }

    public function test_el_formulario_avisa_cuando_solo_falta_avanzar_el_estado(): void
    {
        $unidad = Unidad::factory()->publicada()->create([
            'estado' => EstadoUnidad::Comprada,
            'precio_lista' => 95000,
        ]);

        Livewire::test(EditUnidad::class, ['record' => $unidad->getRouteKey()])
            ->assertSee('Todavía no, por el estado')
            ->assertDontSee('Así no va a aparecer en el portal');
    }

    public function test_el_formulario_confirma_cuando_si_se_va_a_ver(): void
    {
        $unidad = Unidad::factory()->publicada()->create([
            'estado' => EstadoUnidad::Publicada,
            'precio_lista' => 95000,
        ]);

        Livewire::test(EditUnidad::class, ['record' => $unidad->getRouteKey()])
            ->assertSee('Se va a ver en el portal');
    }
}
