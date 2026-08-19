<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
use App\Filament\Resources\Unidades\Pages\CreateUnidad;
use App\Models\Empresa;
use App\Models\Role;
use App\Models\Unidad;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Un concesionario no siempre registra el carro cuando lo compra en subasta.
 * A veces lo hace cuando ya lo tiene enfrente, y al empezar a usar el sistema
 * carga de golpe el inventario que ya tenía en el patio.
 *
 * Si toda unidad naciera en "Comprada", cargar 40 carros existentes exigiría
 * ocho cambios de estado por cada uno.
 */
class RegistroEnCualquierPuntoTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        Tenancy::usar($this->empresa);

        foreach (['ViewAny:Unidad', 'View:Unidad', 'Create:Unidad', 'Update:Unidad'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $usuario = User::factory()->create();
        $usuario->empresas()->attach($this->empresa);

        Tenancy::comoEmpresa($this->empresa, function () use ($usuario) {
            Role::findOrCreate('dueno', 'web')->syncPermissions(Permission::all());
            $usuario->assignRole('dueno');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($usuario);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($this->empresa);
    }

    protected function registrar(array $datos = []): Unidad
    {
        Livewire::test(CreateUnidad::class)
            ->fillForm([
                'vin' => '1HGCM82633A00'.random_int(1000, 9999),
                'stock_no' => 'TEST-'.random_int(100, 999),
                'anio' => 2019,
                'tipo_vehiculo' => 'automovil',
                ...$datos,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        return Unidad::latest('id')->first();
    }

    /** El caso de siempre: se compró en subasta y se registra ahí mismo. */
    public function test_por_defecto_nace_como_comprada(): void
    {
        $unidad = $this->registrar();

        $this->assertSame(EstadoUnidad::Comprada, $unidad->estado);
        $this->assertNull($unidad->fecha_recepcion);
        $this->assertSame('Unidad registrada', $unidad->transiciones()->first()->nota);
    }

    /** El dueño lo registra cuando ya lo tiene enfrente, listo para vender. */
    public function test_se_puede_registrar_una_unidad_que_ya_esta_lista(): void
    {
        $unidad = $this->registrar([
            'estado' => EstadoUnidad::Lista->value,
            'fecha_compra' => now()->subDays(70)->toDateString(),
        ]);

        $this->assertSame(EstadoUnidad::Lista, $unidad->estado);

        // No arranca en cero: ya llevaba tiempo en el patio antes de la ficha.
        $this->assertNotNull($unidad->fecha_recepcion);
        $this->assertNotNull($unidad->fecha_lista);
        $this->assertEqualsWithDelta(70, $unidad->dias_inventario, 1);
    }

    public function test_el_historial_dice_que_entro_directo_en_ese_estado(): void
    {
        $unidad = $this->registrar(['estado' => EstadoUnidad::EnTaller->value]);

        $transicion = $unidad->transiciones()->first();

        $this->assertNull($transicion->estado_anterior);
        $this->assertSame(EstadoUnidad::EnTaller, $transicion->estado_nuevo);
        $this->assertStringContainsString('directamente', $transicion->nota);
    }

    /** Se respeta la fecha de llegada que indique la persona. */
    public function test_respeta_la_fecha_de_recepcion_indicada(): void
    {
        $unidad = $this->registrar([
            'estado' => EstadoUnidad::EnTaller->value,
            'fecha_recepcion' => now()->subDays(12)->toDateString(),
        ]);

        $this->assertSame(now()->subDays(12)->toDateString(), $unidad->fecha_recepcion->toDateString());
    }

    /** Registrar una que va en camino no le pone fecha de llegada al patio. */
    public function test_una_unidad_en_transito_no_recibe_fecha_de_patio(): void
    {
        $unidad = $this->registrar(['estado' => EstadoUnidad::Embarcada->value]);

        $this->assertSame(EstadoUnidad::Embarcada, $unidad->estado);
        $this->assertNull($unidad->fecha_recepcion);
        $this->assertNull($unidad->fecha_lista);
    }

    /** No se ofrece registrar una unidad como ya vendida o dada de baja. */
    public function test_no_se_puede_registrar_directamente_como_vendida(): void
    {
        $estados = collect(EstadoUnidad::cases())->filter->esInventario()->map->value;

        $this->assertFalse($estados->contains(EstadoUnidad::Vendida->value));
        $this->assertFalse($estados->contains(EstadoUnidad::Entregada->value));
        $this->assertFalse($estados->contains(EstadoUnidad::Baja->value));
        $this->assertTrue($estados->contains(EstadoUnidad::Lista->value));
    }
}
