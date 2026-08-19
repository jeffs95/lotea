<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
use App\Enums\TipoPlaca;
use App\Filament\Pages\Levantamiento;
use App\Models\Empresa;
use App\Models\Marca;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\Unidad;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Levantar el inventario de un patio que no tenía ningún control.
 *
 * El cliente no llega con un Excel: llega con carros y con lo que el dueño
 * recuerda. La pantalla tiene que capturar rápido y aceptar fichas a medias,
 * porque detener el recorrido por un VIN que no se ve es lo que hace que nadie
 * termine de cargar su inventario.
 */
class LevantamientoTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected Sucursal $sucursal;

    protected Marca $marca;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle'], 'Patio Roosevelt');
        Tenancy::usar($this->empresa);

        $this->sucursal = Sucursal::first();
        $this->marca = Marca::withoutGlobalScopes()->create(['empresa_id' => null, 'nombre' => 'Toyota', 'slug' => 'toyota']);

        foreach (['ViewAny:Unidad', 'View:Unidad', 'Create:Unidad', 'Update:Unidad', 'View:Levantamiento'] as $permiso) {
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

    protected function capturar(array $datos = [])
    {
        return Livewire::test(Levantamiento::class)
            ->set('sucursalId', $this->sucursal->id)
            ->fillForm([
                'tipo_vehiculo' => 'automovil',
                'marca_id' => $this->marca->id,
                'precio_lista' => 135000,
                'fotos' => [UploadedFile::fake()->image('frente.jpg', 400, 300)],
                ...$datos,
            ])
            ->call('guardarYSeguir');
    }

    public function test_captura_un_carro_con_lo_minimo_y_lo_publica(): void
    {
        $this->capturar()->assertHasNoFormErrors();

        $unidad = Unidad::latest('id')->first();

        $this->assertSame(EstadoUnidad::Lista, $unidad->estado);
        $this->assertSame($this->sucursal->id, $unidad->sucursal_id);
        $this->assertEquals(135000, $unidad->precio_lista);
        $this->assertTrue($unidad->publicado);
        $this->assertTrue($unidad->tieneAlgunaFoto());
    }

    /** Sin precio no se guarda: es el dato que el comprador va a buscar. */
    public function test_el_precio_es_obligatorio(): void
    {
        $this->capturar(['precio_lista' => null])->assertHasFormErrors(['precio_lista']);

        $this->assertSame(0, Unidad::count());
    }

    /** Sin foto tampoco: publicar un carro sin imagen daña la imagen del patio. */
    public function test_la_foto_es_obligatoria(): void
    {
        $this->capturar(['fotos' => []])->assertHasFormErrors(['fotos']);

        $this->assertSame(0, Unidad::count());
    }

    /** El VIN puede no estar a la vista: el recorrido no se detiene por eso. */
    public function test_se_puede_capturar_sin_vin_y_queda_marcado(): void
    {
        $this->capturar(['anio' => 2019])->assertHasNoFormErrors();

        $unidad = Unidad::latest('id')->first();

        $this->assertNull($unidad->vin);
        $this->assertFalse($unidad->estaCompleta());
        $this->assertContains('VIN', $unidad->loQueFalta());

        // Aunque falte el VIN, con precio y foto ya se puede mostrar.
        $this->assertTrue($unidad->publicado);
    }

    public function test_deduce_el_tipo_de_placa_al_capturar(): void
    {
        $this->capturar(['placa' => 'p123abc']);

        $unidad = Unidad::latest('id')->first();

        $this->assertSame('P123ABC', $unidad->placa);
        $this->assertSame(TipoPlaca::Particular, $unidad->tipo_placa);
    }

    public function test_el_formulario_queda_limpio_para_el_siguiente_carro(): void
    {
        $componente = $this->capturar(['anio' => 2019]);

        $componente->assertFormSet(['marca_id' => null, 'precio_lista' => null, 'anio' => null]);
    }

    public function test_lleva_la_cuenta_de_lo_capturado_en_la_sesion(): void
    {
        $componente = Livewire::test(Levantamiento::class)->set('sucursalId', $this->sucursal->id);

        foreach ([120000, 98000] as $precio) {
            $componente
                ->fillForm([
                    'tipo_vehiculo' => 'automovil',
                    'marca_id' => $this->marca->id,
                    'precio_lista' => $precio,
                    'fotos' => [UploadedFile::fake()->image('foto.jpg', 400, 300)],
                ])
                ->call('guardarYSeguir')
                ->assertHasNoFormErrors();
        }

        $this->assertCount(2, $componente->get('capturadas'));
        $this->assertSame(2, Unidad::count());
    }

    public function test_el_historial_dice_que_vino_del_levantamiento(): void
    {
        $this->capturar();

        $this->assertSame(
            'Levantamiento de inventario',
            Unidad::latest('id')->first()->transiciones()->first()->nota,
        );
    }

    // ---- Fichas incompletas ----

    public function test_las_incompletas_se_pueden_listar_aparte(): void
    {
        Unidad::factory()->publicada()->create(['vin' => '1HGCM82633A004352', 'anio' => 2019, 'marca_id' => $this->marca->id]);
        Unidad::factory()->create(['vin' => null, 'anio' => null]);

        $this->assertSame(1, Unidad::incompletas()->count());
    }

    /** El guard vale para cualquier camino, no solo para el formulario. */
    public function test_no_se_puede_publicar_una_unidad_sin_foto_ni_precio(): void
    {
        $unidad = Unidad::factory()->create(['precio_lista' => null]);

        $unidad->update(['publicado' => true]);

        $this->assertFalse($unidad->fresh()->publicado);
        $this->assertFalse($unidad->puedePublicarse());
        $this->assertContains('precio', $unidad->loQueFaltaParaPublicar());
    }

    public function test_una_unidad_con_precio_pero_sin_foto_tampoco_se_publica(): void
    {
        $unidad = Unidad::factory()->create(['precio_lista' => 148000]);

        $unidad->update(['publicado' => true]);

        $this->assertFalse($unidad->fresh()->publicado);
        $this->assertSame(['fotos'], $unidad->loQueFaltaParaPublicar());
    }
}
