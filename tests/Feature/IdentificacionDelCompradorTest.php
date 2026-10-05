<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Support\AlmacenDeArchivos;
use App\Support\RutaDeArchivos;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * El documento de identidad de quien compra.
 *
 * Hace falta porque el traspaso de propiedad queda a nombre del comprador: si
 * años después aparece una multa, un embargo o un accidente con ese vehículo,
 * lo primero que preguntan es a quién se le vendió. El número de DPI escrito a
 * mano en un campo de texto no prueba nada.
 *
 * Y es un documento de identidad, no la foto de un carro: no puede terminar en
 * el cubo que sirve el CDN a cualquiera con el enlace.
 */
class IdentificacionDelCompradorTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        Tenancy::usar($this->empresa);
    }

    protected function clienteConDpi(): Cliente
    {
        $cliente = Cliente::factory()->create(['nombre' => 'María López']);

        $cliente->addMedia(UploadedFile::fake()->image('dpi-frente.jpg', 1200, 800))
            ->toMediaCollection('identificacion');

        return $cliente->refresh();
    }

    public function test_el_cliente_guarda_la_foto_de_su_documento(): void
    {
        $cliente = $this->clienteConDpi();

        $this->assertTrue($cliente->tieneIdentificacion());
        $this->assertSame(1, $cliente->getMedia('identificacion')->count());
    }

    public function test_sin_foto_se_sabe_que_falta(): void
    {
        $this->assertFalse(Cliente::factory()->create()->tieneIdentificacion());
    }

    /** Las dos caras, o el pasaporte si es extranjero. */
    public function test_admite_varias_imagenes(): void
    {
        $cliente = Cliente::factory()->create();

        foreach (['frente', 'reverso'] as $cara) {
            $cliente->addMedia(UploadedFile::fake()->image("dpi-{$cara}.jpg", 1200, 800))
                ->toMediaCollection('identificacion');
        }

        $this->assertSame(2, $cliente->refresh()->getMedia('identificacion')->count());
    }

    /**
     * El que de verdad importa: esto no puede acabar en el cubo público.
     *
     * Ahí los archivos los sirve el CDN a cualquiera que tenga el enlace, sin
     * pasar por ninguna autorización. Para la foto de un carro está bien; para
     * el documento de identidad de un comprador, no.
     */
    public function test_el_documento_va_al_disco_privado(): void
    {
        $media = $this->clienteConDpi()->getFirstMedia('identificacion');

        $this->assertSame(AlmacenDeArchivos::discoPrivado(), $media->disk);
    }

    /** Y su URL pasa por donde se decide quién puede verla. */
    public function test_la_url_no_apunta_al_cdn(): void
    {
        config([
            'lotea.discos.publico' => 'cubo_publico_falso',
            'filesystems.disks.cubo_publico_falso' => [
                'driver' => 's3',
                'url' => 'https://archivos.ejemplo.dev',
            ],
        ]);

        $url = $this->clienteConDpi()->getFirstMedia('identificacion')->getUrl();

        $this->assertStringNotContainsString('archivos.ejemplo.dev', $url,
            'El documento de identidad sale por el CDN, accesible a cualquiera con el enlace.');
        $this->assertStringContainsString('/archivo/', $url);
    }

    /**
     * Y tampoco cuando el disco privado se quedó sin configurar.
     *
     * Es el escenario real de un despliegue a medias: se define el disco de
     * archivos y el público, se olvida el privado, y los dos pasan a ser el
     * mismo cubo detrás del CDN. Nada falla, nada avisa, y los documentos de
     * identidad quedan accesibles con solo el enlace. Por eso la colección
     * decide, y no únicamente la variable de entorno.
     */
    public function test_la_url_no_apunta_al_cdn_aunque_falte_configurar_el_disco_privado(): void
    {
        $media = $this->clienteConDpi()->getFirstMedia('identificacion');

        config([
            'lotea.discos.publico' => null,
            'lotea.discos.privado' => null,
            "filesystems.disks.{$media->disk}.driver" => 's3',
            "filesystems.disks.{$media->disk}.url" => 'https://archivos.ejemplo.dev',
        ]);

        $this->assertSame($media->disk, AlmacenDeArchivos::discoPublico(),
            'El montaje del caso no reproduce la mala configuración que se quiere cubrir.');

        $this->assertStringNotContainsString('archivos.ejemplo.dev', $media->getUrl());
        $this->assertStringContainsString('/archivo/', $media->getUrl());
    }

    /** Cada concesionario tiene su carpeta, para poder borrarla entera. */
    public function test_el_documento_se_archiva_bajo_su_concesionario(): void
    {
        $media = $this->clienteConDpi()->getFirstMedia('identificacion');

        $this->assertStringStartsWith(
            "{$this->empresa->slug}/clientes/{$media->model_id}/identificacion/",
            (new RutaDeArchivos)->getPath($media),
        );
    }

    /** No se mezclan los compradores de un concesionario con los de otro. */
    public function test_el_documento_no_cruza_de_concesionario(): void
    {
        $mio = $this->clienteConDpi();

        $otra = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Norte']);

        $ajenos = Tenancy::comoEmpresa($otra, fn () => Cliente::query()->get());

        $this->assertFalse($ajenos->contains('id', $mio->getKey()));
    }
}
