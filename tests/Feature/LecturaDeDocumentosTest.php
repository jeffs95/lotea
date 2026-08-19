<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Actions\ResolverCatalogoVehiculo;
use App\Models\Linea;
use App\Models\Marca;
use App\Services\LectorDeDocumentos;
use App\Services\ValidadorDeDatosLeidos;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * La IA se equivoca y a veces inventa. Todo lo que devuelve pasa por el
 * validador antes de tocar el formulario, porque un VIN mal puesto sigue al
 * carro toda su vida.
 */
class LecturaDeDocumentosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']));
    }

    // ---- El validador ----

    public function test_acepta_un_vin_valido_y_lo_normaliza(): void
    {
        $limpio = ValidadorDeDatosLeidos::limpiar(['vin' => ' 1hgcm82633a-004352 ']);

        $this->assertSame('1HGCM82633A004352', $limpio['vin']);
    }

    /** Un VIN no tiene I, O ni Q, y siempre mide 17. */
    public function test_descarta_un_vin_que_no_cumple_la_regla(): void
    {
        foreach (['ABC123', '1HGCM82633A00435', 'IOQCM82633A004352'] as $malo) {
            $this->assertArrayNotHasKey('vin', ValidadorDeDatosLeidos::limpiar(['vin' => $malo]));
        }
    }

    public function test_descarta_valores_que_no_estan_en_nuestras_listas(): void
    {
        $limpio = ValidadorDeDatosLeidos::limpiar([
            'transmision' => 'tiptronic',
            'combustible' => 'gasolina',
            'carroceria' => 'nave espacial',
        ]);

        $this->assertArrayNotHasKey('transmision', $limpio);
        $this->assertArrayNotHasKey('carroceria', $limpio);
        $this->assertSame('gasolina', $limpio['combustible']);
    }

    public function test_descarta_numeros_imposibles(): void
    {
        $limpio = ValidadorDeDatosLeidos::limpiar([
            'anio' => 2045,
            'cilindros' => 99,
            'puertas' => 12,
            'odometro' => 'ochenta mil',
        ]);

        $this->assertSame([], $limpio);
    }

    /** Los documentos vienen en mayúsculas sostenidas y en pantalla se ve mal. */
    public function test_arregla_el_texto_que_viene_todo_en_mayusculas(): void
    {
        $limpio = ValidadorDeDatosLeidos::limpiar(['marca' => 'TOYOTA', 'color' => 'BLANCO PERLA']);

        $this->assertSame('Toyota', $limpio['marca']);
        $this->assertSame('Blanco Perla', $limpio['color']);
    }

    /** Una placa no es un nombre: P123ABC no puede volverse P123Abc. */
    public function test_no_capitaliza_la_placa(): void
    {
        $limpio = ValidadorDeDatosLeidos::limpiar(['placa' => ' p123abc ']);

        $this->assertSame('P123ABC', $limpio['placa']);
    }

    // ---- El catálogo ----

    public function test_reutiliza_la_marca_y_la_linea_que_ya_existen(): void
    {
        $toyota = Marca::withoutGlobalScopes()->create(['empresa_id' => null, 'nombre' => 'Toyota', 'slug' => 'toyota']);
        $rav4 = Linea::withoutGlobalScopes()->create(['empresa_id' => null, 'marca_id' => $toyota->id, 'nombre' => 'RAV4', 'slug' => 'rav4']);

        $resuelto = app(ResolverCatalogoVehiculo::class)->ejecutar('TOYOTA', 'rav4');

        $this->assertSame($toyota->id, $resuelto['marca_id']);
        $this->assertSame($rav4->id, $resuelto['linea_id']);
        $this->assertSame(1, Marca::count());
    }

    /** Mejor un catálogo que crece que una ficha incompleta. */
    public function test_crea_la_marca_y_la_linea_si_no_existen(): void
    {
        $resuelto = app(ResolverCatalogoVehiculo::class)->ejecutar('BYD', 'Song Plus');

        $this->assertNotNull($resuelto['marca_id']);
        $this->assertSame('Byd', Marca::find($resuelto['marca_id'])->nombre);
        $this->assertSame('Song Plus', Linea::find($resuelto['linea_id'])->nombre);
    }

    public function test_sin_marca_no_se_inventa_una_linea_suelta(): void
    {
        $resuelto = app(ResolverCatalogoVehiculo::class)->ejecutar(null, 'RAV4');

        $this->assertNull($resuelto['marca_id']);
        $this->assertNull($resuelto['linea_id']);
        $this->assertSame(0, Linea::count());
    }

    // ---- El lector ----

    public function test_la_funcion_se_apaga_sola_si_no_hay_llave(): void
    {
        config(['services.openrouter.key' => null]);

        $this->assertFalse(app(LectorDeDocumentos::class)->estaDisponible());
    }

    public function test_lee_un_documento_y_devuelve_los_datos_limpios(): void
    {
        config(['services.openrouter.key' => 'llave-de-prueba']);

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
                    'combustible' => 'diesel de avión',   // inventado: se descarta
                ],
                'aviso' => null,
            ])]]]]),
        ]);

        $resultado = app(LectorDeDocumentos::class)->leer($this->imagenDePrueba());

        $this->assertSame('tarjeta_circulacion', $resultado['tipo_documento']);
        $this->assertSame('1HGCM82633A004352', $resultado['datos']['vin']);
        $this->assertSame('Toyota', $resultado['datos']['marca']);
        $this->assertSame(2019, $resultado['datos']['anio']);
        $this->assertArrayNotHasKey('combustible', $resultado['datos']);
    }

    /** El modelo devuelve el JSON envuelto en ``` aunque se le pida que no. */
    public function test_entiende_la_respuesta_aunque_venga_envuelta_en_backticks(): void
    {
        config(['services.openrouter.key' => 'llave-de-prueba']);

        Http::fake([
            '*' => Http::response(['choices' => [['message' => ['content' =>
                "```json\n".json_encode(['datos' => ['anio' => 2020]])."\n```",
            ]]]]),
        ]);

        $resultado = app(LectorDeDocumentos::class)->leer($this->imagenDePrueba());

        $this->assertSame(2020, $resultado['datos']['anio']);
    }

    public function test_avisa_con_claridad_cuando_se_acaba_el_credito(): void
    {
        config(['services.openrouter.key' => 'llave-de-prueba']);

        Http::fake(['*' => Http::response(['error' => ['message' => 'Insufficient credits']], 402)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Se agotó el crédito');

        app(LectorDeDocumentos::class)->leer($this->imagenDePrueba());
    }

    public function test_avisa_cuando_la_llave_no_sirve(): void
    {
        config(['services.openrouter.key' => 'llave-mala']);

        Http::fake(['*' => Http::response(['error' => ['message' => 'No auth']], 401)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no es válida');

        app(LectorDeDocumentos::class)->leer($this->imagenDePrueba());
    }

    public function test_no_revienta_si_el_modelo_devuelve_cualquier_cosa(): void
    {
        config(['services.openrouter.key' => 'llave-de-prueba']);

        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'No pude leer la imagen.']]]])]);

        $this->expectException(RuntimeException::class);

        app(LectorDeDocumentos::class)->leer($this->imagenDePrueba());
    }

    public function test_manda_la_imagen_y_el_modelo_configurado(): void
    {
        config([
            'services.openrouter.key' => 'llave-de-prueba',
            'services.openrouter.modelo' => 'qwen/qwen2.5-vl-72b-instruct',
        ]);

        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"datos":{}}']]]])]);

        app(LectorDeDocumentos::class)->leer($this->imagenDePrueba());

        Http::assertSent(function (Request $peticion) {
            $cuerpo = $peticion->data();
            $contenido = $cuerpo['messages'][0]['content'];

            return $cuerpo['model'] === 'qwen/qwen2.5-vl-72b-instruct'
                && $cuerpo['temperature'] === 0
                && $contenido[0]['type'] === 'text'
                && $contenido[1]['type'] === 'image_url'
                && str_starts_with($contenido[1]['image_url']['url'], 'data:image/');
        });
    }

    protected function imagenDePrueba(): string
    {
        Storage::fake('local');

        $ruta = storage_path('app/documento-de-prueba.png');

        $imagen = imagecreatetruecolor(40, 25);
        imagefill($imagen, 0, 0, imagecolorallocate($imagen, 255, 255, 255));
        imagepng($imagen, $ruta);
        imagedestroy($imagen);

        return $ruta;
    }
}
