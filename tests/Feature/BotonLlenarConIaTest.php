<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Filament\Resources\Unidades\Pages\CreateUnidad;
use App\Models\Empresa;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Ejecuta el botón tal como lo usa una persona.
 *
 * Existe porque la primera versión reventaba con "Call to a member function
 * makeSetUtility() on null": los tests del servicio pasaban pero el botón no
 * servía. Probar la acción y no solo el servicio es la diferencia.
 */
class BotonLlenarConIaTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        // El botón es un add-on: sin el módulo contratado no existe.
        $plan = Plan::create([
            'nombre' => 'Con IA',
            'slug' => 'con-ia',
            'precio_mensual' => 1295,
            'modulos' => ['unidades', 'ia'],
        ]);

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle', 'plan_id' => $plan->id]);
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

        config(['services.openrouter.key' => 'llave-de-prueba']);

        Storage::fake('local');
    }

    public function test_el_boton_llena_el_formulario_con_lo_que_leyo(): void
    {
        Http::fake([
            '*' => Http::response(['choices' => [['message' => ['content' => json_encode([
                'tipo_documento' => 'tarjeta_circulacion',
                'datos' => [
                    'vin' => '1HGCM82633A004352',
                    'marca' => 'TOYOTA',
                    'linea' => 'RAV4',
                    'anio' => 2019,
                    'color' => 'BLANCO',
                    'transmision' => 'automatica',
                    'cilindros' => 4,
                    'puertas' => 5,
                    'placa' => 'P123ABC',
                ],
                'aviso' => null,
            ])]]]]),
        ]);

        Livewire::test(CreateUnidad::class)
            ->callAction('leerDocumento', data: ['documento' => [$this->documentoSubido()]])
            ->assertHasNoActionErrors()
            ->assertFormSet([
                'vin' => '1HGCM82633A004352',
                'anio' => 2019,
                'color' => 'Blanco',
                'transmision' => 'automatica',
                'cilindros' => 4,
                'puertas' => 5,
                'placa' => 'P123ABC',
                'tipo_placa' => 'P',
            ]);
    }

    public function test_resuelve_la_marca_y_la_linea_a_sus_ids(): void
    {
        Http::fake([
            '*' => Http::response(['choices' => [['message' => ['content' => json_encode([
                'datos' => ['marca' => 'Mazda', 'linea' => 'CX-5'],
            ])]]]]),
        ]);

        $estado = Livewire::test(CreateUnidad::class)
            ->callAction('leerDocumento', data: ['documento' => [$this->documentoSubido()]])
            ->assertHasNoActionErrors()
            ->get('data');

        $this->assertNotNull($estado['marca_id']);
        $this->assertNotNull($estado['linea_id']);
        $this->assertSame('Mazda', \App\Models\Marca::find($estado['marca_id'])->nombre);
    }

    /** Lo que la persona ya escribió no se pierde al leer el documento. */
    public function test_no_borra_lo_que_ya_estaba_escrito(): void
    {
        Http::fake([
            '*' => Http::response(['choices' => [['message' => ['content' => json_encode([
                'datos' => ['anio' => 2019],
            ])]]]]),
        ]);

        Livewire::test(CreateUnidad::class)
            ->fillForm(['stock_no' => 'MI-STOCK', 'precio_lista' => 148000])
            ->callAction('leerDocumento', data: ['documento' => [$this->documentoSubido()]])
            ->assertFormSet([
                'stock_no' => 'MI-STOCK',
                'precio_lista' => 148000,
                'anio' => 2019,
            ]);
    }

    /** Dos documentos del mismo carro, cada uno con su parte. */
    public function test_acepta_varios_documentos_y_combina_lo_que_dicen(): void
    {
        Http::fake([
            '*' => Http::response(['choices' => [['message' => ['content' => json_encode([
                'documentos' => ['titulo_usa', 'hoja_subasta'],
                'datos' => [
                    'vin' => '1HGCM82633A004352',
                    'anio' => 2019,
                    'odometro' => 62400,
                    'tipo_titulo' => 'salvage',
                    'tipo_dano' => 'Front end',
                ],
                'aviso' => null,
            ])]]]]),
        ]);

        Livewire::test(CreateUnidad::class)
            ->callAction('leerDocumento', data: ['documento' => [
                $this->documentoSubido('titulo.png'),
                $this->documentoSubido('subasta.png'),
            ]])
            ->assertHasNoActionErrors()
            ->assertFormSet([
                'vin' => '1HGCM82633A004352',
                'anio' => 2019,
                'odometro' => 62400,
                'tipo_titulo' => 'salvage',
                'tipo_dano' => 'Front end',
            ]);
    }

    public function test_borra_todos_los_documentos_despues_de_leerlos(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"datos":{}}']]]])]);

        $rutas = [$this->documentoSubido('a.png'), $this->documentoSubido('b.png')];

        Livewire::test(CreateUnidad::class)
            ->callAction('leerDocumento', data: ['documento' => $rutas])
            ->assertHasNoActionErrors();

        foreach ($rutas as $ruta) {
            Storage::disk('local')->assertMissing($ruta);
        }
    }

    public function test_avisa_sin_romperse_cuando_el_servicio_falla(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'sin credito']], 402)]);

        Livewire::test(CreateUnidad::class)
            ->callAction('leerDocumento', data: ['documento' => [$this->documentoSubido()]])
            ->assertHasNoActionErrors()
            ->assertNotified();
    }

    /** El documento del cliente no se queda en el servidor. */
    public function test_borra_el_archivo_despues_de_leerlo(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"datos":{}}']]]])]);

        $ruta = $this->documentoSubido();

        Livewire::test(CreateUnidad::class)
            ->callAction('leerDocumento', data: ['documento' => [$ruta]])
            ->assertHasNoActionErrors();

        Storage::disk('local')->assertMissing($ruta);
    }

    /** Deja una imagen en el disk como la habría dejado el FileUpload. */
    protected function documentoSubido(string $nombre = 'documento.png'): string
    {
        $imagen = imagecreatetruecolor(60, 40);
        imagefill($imagen, 0, 0, imagecolorallocate($imagen, 255, 255, 255));

        ob_start();
        imagepng($imagen);
        $binario = ob_get_clean();
        imagedestroy($imagen);

        $ruta = 'lecturas/'.$nombre;
        Storage::disk('local')->put($ruta, $binario);

        return $ruta;
    }
}
