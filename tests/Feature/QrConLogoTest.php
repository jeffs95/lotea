<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\Empresa;
use App\Models\Unidad;
use App\Support\QrDeUnidad;
use App\Support\Tenancy;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * El QR del parabrisas con el logo del concesionario en el centro.
 *
 * Un logo encima tapa módulos del código. Si se agranda o si se baja el nivel
 * de corrección de errores, el QR deja de escanear —y nadie lo nota hasta que
 * un comprador está frente al carro con el teléfono en la mano y no pasa nada.
 */
class QrConLogoTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected Unidad $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Gómez']);

        Tenancy::usar($this->empresa);

        $this->unidad = Unidad::factory()->create(['stock_no' => '0001']);
    }

    protected function ponerLogo(): void
    {
        $logo = UploadedFile::fake()->image('logo.png', 320, 120);

        $this->empresa->update(['logo_path' => $logo->store('marcas', 'public')]);
        $this->unidad->refresh();
    }

    public function test_sin_logo_configurado_el_qr_sale_limpio(): void
    {
        $svg = QrDeUnidad::svg($this->unidad, 300);

        $this->assertStringNotContainsString('<image', $svg);
        $this->assertStringContainsString('<svg', $svg);
    }

    public function test_con_logo_configurado_el_qr_lo_lleva_en_el_centro(): void
    {
        $this->ponerLogo();

        $svg = QrDeUnidad::svg($this->unidad, 300);

        $this->assertStringContainsString('<image', $svg);
        // Incrustado y no enlazado: el SVG viaja dentro de un data URI y ahí
        // una imagen externa no cargaría nunca.
        $this->assertStringContainsString('href="data:image/png;base64,', $svg);
    }

    public function test_el_logo_va_sobre_un_cuadro_blanco(): void
    {
        $this->ponerLogo();

        $svg = QrDeUnidad::svg($this->unidad, 300);

        // El cuadro es lo que separa el logo de los módulos y le da al lector
        // una zona limpia que puede reconstruir.
        $this->assertMatchesRegularExpression('/<rect[^>]+rx="\d+"[^>]+fill="#ffffff"/', $svg);
    }

    public function test_se_puede_pedir_el_qr_sin_logo_aunque_el_cliente_tenga_uno(): void
    {
        $this->ponerLogo();

        $this->assertStringNotContainsString('<image', QrDeUnidad::svg($this->unidad, 300, conLogo: false));
    }

    /** Un logo ilegible no puede dejar a nadie sin etiqueta. */
    public function test_un_logo_que_no_esta_en_el_disco_no_rompe_el_qr(): void
    {
        $this->empresa->update(['logo_path' => 'marcas/borrado.png']);
        $this->unidad->refresh();

        $svg = QrDeUnidad::svg($this->unidad, 300);

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringNotContainsString('<image', $svg);
    }

    public function test_el_logo_no_pasa_de_la_cuarta_parte_del_codigo(): void
    {
        $this->ponerLogo();

        preg_match('/<image x="(\d+)"[^>]*width="(\d+)"/', QrDeUnidad::svg($this->unidad, 300), $m);

        $this->assertNotEmpty($m, 'No se encontró el logo en el SVG.');
        $this->assertLessThanOrEqual(0.25, (int) $m[2] / 300);

        // Y centrado: descuadrado taparía uno de los tres ojos del código.
        $this->assertEqualsWithDelta(150, (int) $m[1] + (int) $m[2] / 2, 2);
    }

    /**
     * La prueba que de verdad importa: se compone un código igual que el de la
     * etiqueta —misma corrección de errores, mismo tamaño de logo— y se
     * decodifica. Si alguien agranda el logo, este test truena.
     */
    public function test_un_codigo_con_el_logo_encima_todavia_se_puede_leer(): void
    {
        $destino = QrDeUnidad::url($this->unidad);

        $png = (new QRCode(new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => EccLevel::H,
            'scale' => 10,
            'imageBase64' => false,
        ])))->render($destino);

        $conLogo = $this->pegarLogoEncima($png);

        $this->assertSame($destino, (string) (new QRCode)->readFromBlob($conLogo));
    }

    /** Y sin el logo, por supuesto, también. */
    public function test_el_codigo_sin_logo_se_lee(): void
    {
        $destino = QrDeUnidad::url($this->unidad);

        $png = (new QRCode(new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => EccLevel::H,
            'scale' => 10,
            'imageBase64' => false,
        ])))->render($destino);

        $this->assertSame($destino, (string) (new QRCode)->readFromBlob($png));
    }

    /** Tapa el centro con un cuadro blanco del mismo tamaño que la etiqueta. */
    protected function pegarLogoEncima(string $png): string
    {
        $imagen = imagecreatefromstring($png);
        $lado = imagesx($imagen);

        $caja = (int) round($lado * QrDeUnidad::PROPORCION_DEL_LOGO * 1.32);
        $origen = (int) round(($lado - $caja) / 2);

        imagefilledrectangle(
            $imagen,
            $origen, $origen,
            $origen + $caja, $origen + $caja,
            imagecolorallocate($imagen, 255, 255, 255),
        );

        ob_start();
        imagepng($imagen);
        $resultado = (string) ob_get_clean();

        imagedestroy($imagen);

        return $resultado;
    }
}
