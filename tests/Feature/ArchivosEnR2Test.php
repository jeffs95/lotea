<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\Empresa;
use App\Models\Unidad;
use App\Models\User;
use App\Support\AlmacenDeArchivos;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Los archivos repartidos entre los dos cubos de R2.
 *
 * Lo que se prueba aquí no es que R2 funcione —eso es de Cloudflare— sino la
 * decisión de qué va a cada lado y cómo sale cada cosa: las fotos del catálogo
 * por su dominio, sin tocar la aplicación; los documentos con enlace firmado,
 * después de que la aplicación diga que sí.
 */
class ArchivosEnR2Test extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected Unidad $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Dos discos separados como en producción, pero guardando en local: no
         * hace falta un R2 de verdad para probar a dónde va cada archivo y qué
         * URL le toca. El «driver» sí dice s3, porque de eso depende la
         * decisión que se está probando.
         */
        Storage::fake('r2_falso_publico');
        Storage::fake('r2_falso_privado');

        config([
            'lotea.discos.publico' => 'r2_falso_publico',
            'lotea.discos.privado' => 'r2_falso_privado',
            'filesystems.disks.r2_falso_publico.driver' => 's3',
            'filesystems.disks.r2_falso_publico.url' => 'https://archivos.lotea.dev',
            'filesystems.disks.r2_falso_privado.driver' => 's3',
        ]);

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        Tenancy::usar($this->empresa);

        $this->unidad = Unidad::factory()->create(['precio_lista' => 90000]);
    }

    /** Una imagen de verdad: las colecciones de fotos generan conversiones. */
    protected function agregar(string $coleccion): Media
    {
        $archivo = $coleccion === 'documentos'
            ? UploadedFile::fake()->create($coleccion.'.pdf', 8, 'application/pdf')
            : UploadedFile::fake()->image($coleccion.'.jpg', 400, 300);

        return $this->unidad->addMedia($archivo)->toMediaCollection($coleccion);
    }

    // ── Qué va a cada cubo ──────────────────────────────────────────────────

    public function test_las_fotos_del_catalogo_van_al_cubo_publico(): void
    {
        $this->assertSame('r2_falso_publico', $this->agregar('fotos')->disk);
    }

    /**
     * Los documentos llevan datos del propietario y las fotos de subasta
     * muestran el carro golpeado, antes del taller. Ninguna de las dos cosas
     * puede quedar en un cubo que sirve el CDN a cualquiera con el enlace.
     */
    public function test_los_documentos_y_las_fotos_de_subasta_van_al_privado(): void
    {
        $this->assertSame('r2_falso_privado', $this->agregar('documentos')->disk);
        $this->assertSame('r2_falso_privado', $this->agregar('fotos_subasta')->disk);
    }

    // ── Cómo sale cada una ──────────────────────────────────────────────────

    /** Lo que quita el peso de encima: la foto no pasa por la aplicación. */
    public function test_la_foto_publica_apunta_al_dominio_del_cdn(): void
    {
        $media = $this->agregar('fotos');

        $url = $media->getUrl();

        $this->assertStringStartsWith('https://archivos.lotea.dev/', $url);
        $this->assertStringNotContainsString('/archivo/', $url, 'La foto sigue pasando por la aplicación.');
        $this->assertStringContainsString(AlmacenDeArchivos::rutaDe($media), $url);
    }

    /** El documento no: ese pasa por donde se decide quién puede verlo. */
    public function test_el_documento_sigue_pasando_por_la_autorizacion(): void
    {
        $url = $this->agregar('documentos')->getUrl();

        $this->assertStringContainsString('/archivo/', $url);
        $this->assertStringNotContainsString('archivos.lotea.dev', $url);
    }

    /** Y un extraño no lo abre por más que adivine la dirección. */
    public function test_un_extrano_no_abre_un_documento(): void
    {
        $media = $this->agregar('documentos');

        $this->get("/archivo/{$media->getKey()}")->assertForbidden();
    }

    /**
     * Y a la gente del concesionario se le manda al archivo con un enlace
     * firmado, en vez de que la aplicación se lo baje y se lo reenvíe.
     *
     * La autorización sigue siendo suya: lo que cambia es quién mueve los
     * bytes. Por eso responde con una redirección y no con el archivo.
     */
    public function test_a_la_gente_del_concesionario_se_le_da_un_enlace_firmado(): void
    {
        $media = $this->agregar('documentos');

        $usuario = User::factory()->create();
        $usuario->empresas()->attach($this->empresa);

        $respuesta = $this->actingAs($usuario)->get("/archivo/{$media->getKey()}");

        $respuesta->assertRedirect();
        $this->assertStringNotContainsString(
            '/archivo/',
            (string) $respuesta->headers->get('Location'),
            'La redirección vuelve a la aplicación en lugar de ir al almacenamiento.',
        );
    }

    /**
     * La URL pública no puede depender de que la app esté levantada ni de
     * quién la pida: es la misma para el portal del cliente y para su panel.
     */
    public function test_la_url_publica_es_la_misma_para_todos(): void
    {
        $media = $this->agregar('fotos');
        $sinSesion = $media->getUrl();

        $usuario = User::factory()->create();
        $usuario->empresas()->attach($this->empresa);
        $this->actingAs($usuario);

        $this->assertSame($sinSesion, $media->fresh()->getUrl());
    }

    /**
     * Mientras el dominio no esté conectado, la foto sigue saliendo por la
     * aplicación en vez de apuntar a una dirección que no responde.
     */
    public function test_sin_dominio_de_cdn_la_foto_vuelve_a_pasar_por_la_aplicacion(): void
    {
        $media = $this->agregar('fotos');

        config(['filesystems.disks.r2_falso_publico.url' => null]);

        $this->assertStringContainsString('/archivo/', $media->fresh()->getUrl());
    }
}
