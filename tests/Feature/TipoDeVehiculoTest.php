<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
use App\Enums\TipoVehiculo;
use App\Models\Empresa;
use App\Models\Unidad;
use App\Services\LectorDeDocumentos;
use App\Services\ValidadorDeDatosLeidos;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Una moto no tiene puertas ni color de interior, y su dato principal es la
 * cilindrada. Pedir la ficha de un carro para una moto hace que el vendedor
 * deje campos vacíos o, peor, invente.
 */
class TipoDeVehiculoTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar([
            'nombre' => 'Autos del Valle',
            'slug' => 'autos-del-valle',
        ]);

        Tenancy::usar($this->empresa);
    }

    public function test_lo_que_ya_existia_queda_como_automovil(): void
    {
        $this->assertSame(TipoVehiculo::Automovil, Unidad::factory()->create()->tipo_vehiculo);
    }

    public function test_una_moto_no_pide_puertas_ni_color_interior_ni_traccion(): void
    {
        $moto = TipoVehiculo::Motocicleta;

        $this->assertFalse($moto->aplica('puertas'));
        $this->assertFalse($moto->aplica('color_interior'));
        $this->assertFalse($moto->aplica('traccion'));
        $this->assertTrue($moto->destacaCilindrada());
    }

    public function test_un_automovil_si_pide_todo_eso(): void
    {
        $auto = TipoVehiculo::Automovil;

        $this->assertTrue($auto->aplica('puertas'));
        $this->assertTrue($auto->aplica('color_interior'));
        $this->assertTrue($auto->aplica('traccion'));
        $this->assertFalse($auto->destacaCilindrada());
    }

    /** Un camión tampoco lleva color de interior, pero sí puertas. */
    public function test_el_camion_tiene_su_propia_ficha(): void
    {
        $camion = TipoVehiculo::Camion;

        $this->assertFalse($camion->aplica('color_interior'));
        $this->assertTrue($camion->aplica('puertas'));
        $this->assertArrayHasKey('cabezal', $camion->carrocerias());
    }

    public function test_cada_tipo_ofrece_sus_propias_carrocerias(): void
    {
        $this->assertArrayHasKey('scooter', TipoVehiculo::Motocicleta->carrocerias());
        $this->assertArrayNotHasKey('scooter', TipoVehiculo::Automovil->carrocerias());

        $this->assertArrayHasKey('sedan', TipoVehiculo::Automovil->carrocerias());
        $this->assertArrayNotHasKey('sedan', TipoVehiculo::Motocicleta->carrocerias());
    }

    /** La moto lleva semiautomática; el auto, CVT. */
    public function test_las_transmisiones_tambien_cambian(): void
    {
        $this->assertArrayHasKey('semiautomatica', TipoVehiculo::Motocicleta->transmisiones());
        $this->assertArrayNotHasKey('cvt', TipoVehiculo::Motocicleta->transmisiones());
        $this->assertArrayHasKey('cvt', TipoVehiculo::Automovil->transmisiones());
    }

    // ---- Lectura de documentos ----

    public function test_el_validador_acepta_el_tipo_y_la_cilindrada(): void
    {
        $limpio = ValidadorDeDatosLeidos::limpiar([
            'tipo_vehiculo' => 'motocicleta',
            'cilindrada_cc' => 150,
            'carroceria' => 'scooter',
            'cilindros' => 1,
        ]);

        $this->assertSame('motocicleta', $limpio['tipo_vehiculo']);
        $this->assertSame(150, $limpio['cilindrada_cc']);
        $this->assertSame('scooter', $limpio['carroceria']);
        $this->assertSame(1, $limpio['cilindros']);
    }

    public function test_el_validador_descarta_una_cilindrada_imposible(): void
    {
        $this->assertArrayNotHasKey('cilindrada_cc', ValidadorDeDatosLeidos::limpiar(['cilindrada_cc' => 45000]));
        $this->assertArrayNotHasKey('tipo_vehiculo', ValidadorDeDatosLeidos::limpiar(['tipo_vehiculo' => 'lancha']));
    }

    public function test_la_ia_puede_registrar_una_moto_completa(): void
    {
        config(['services.openrouter.key' => 'llave-de-prueba']);

        Http::fake([
            '*' => Http::response(['choices' => [['message' => ['content' => json_encode([
                'documentos' => ['tarjeta_circulacion'],
                'datos' => [
                    'tipo_vehiculo' => 'motocicleta',
                    'marca' => 'HONDA',
                    'linea' => 'CB 190R',
                    'anio' => 2022,
                    'cilindrada_cc' => 184,
                    'carroceria' => 'naked',
                    'transmision' => 'manual',
                    'color' => 'ROJO',
                    'puertas' => 4,        // no aplica a una moto
                    'traccion' => '4x2',   // tampoco
                ],
            ])]]]]),
        ]);

        $resultado = app(LectorDeDocumentos::class)->leer([$this->imagen()]);

        $this->assertSame('motocicleta', $resultado['datos']['tipo_vehiculo']);
        $this->assertSame(184, $resultado['datos']['cilindrada_cc']);
        $this->assertSame('naked', $resultado['datos']['carroceria']);
        $this->assertSame('Honda', $resultado['datos']['marca']);
    }

    /** El prompt tiene que decirle explícitamente qué no aplica a una moto. */
    public function test_al_modelo_se_le_explica_la_ficha_de_una_moto(): void
    {
        config(['services.openrouter.key' => 'llave-de-prueba']);

        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"datos":{}}']]]])]);

        app(LectorDeDocumentos::class)->leer([$this->imagen()]);

        Http::assertSent(function ($peticion) {
            $texto = $peticion->data()['messages'][0]['content'][0]['text'];

            return str_contains($texto, 'MOTOCICLETA')
                && str_contains($texto, 'cilindrada_cc')
                && str_contains($texto, 'scooter');
        });
    }

    // ---- Portal ----

    public function test_el_portal_muestra_la_ficha_de_la_moto_sin_puertas(): void
    {
        $moto = Unidad::factory()->publicada()->create([
            'tipo_vehiculo' => TipoVehiculo::Motocicleta,
            'estado' => EstadoUnidad::Publicada,
            'slug' => 'honda-cb190r-2022',
            'cilindrada_cc' => 184,
            'carroceria' => 'naked',
            'transmision' => 'manual',
            'puertas' => 4,            // dato viejo que no debe salir
            'color_interior' => 'Negro',
        ]);

        $this->get("/v/{$this->empresa->slug}/vehiculos/{$moto->slug}")
            ->assertOk()
            ->assertSee('184 cc')
            ->assertSee('Naked / calle', escape: false)
            ->assertDontSee('Puertas');
    }

    public function test_el_catalogo_se_puede_filtrar_por_tipo(): void
    {
        Unidad::factory()->publicada()->create([
            'tipo_vehiculo' => TipoVehiculo::Motocicleta,
            'estado' => EstadoUnidad::Publicada,
            'stock_no' => 'MOTO-1',
            'slug' => 'una-moto',
        ]);

        Unidad::factory()->publicada()->create([
            'tipo_vehiculo' => TipoVehiculo::Automovil,
            'estado' => EstadoUnidad::Publicada,
            'stock_no' => 'AUTO-1',
            'slug' => 'un-auto',
        ]);

        $this->get("/v/{$this->empresa->slug}/vehiculos?tipo_vehiculo=motocicleta")
            ->assertOk()
            ->assertSee('MOTO-1')
            ->assertDontSee('AUTO-1');
    }

    protected function imagen(): string
    {
        Storage::fake('local');

        $ruta = storage_path('app/doc-moto.png');

        $imagen = imagecreatetruecolor(40, 25);
        imagefill($imagen, 0, 0, imagecolorallocate($imagen, 255, 255, 255));
        imagepng($imagen, $ruta);
        imagedestroy($imagen);

        return $ruta;
    }
}
