<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
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
 * Los archivos viviendo fuera del servidor web.
 *
 * Un disco FTP no tiene URL pública, así que las fotos y los documentos pasan
 * por una ruta que decide quién puede verlos. Eso cierra algo que estaba mal:
 * con el disco público, el título de un carro quedaba accesible a quien diera
 * con la URL.
 *
 * El disco se finge como local sin URL, que es como se comporta el FTP para
 * este código: no hace falta un servidor de verdad para probar la lógica.
 */
class ArchivosEnFtpTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected Empresa $otraEmpresa;

    protected Unidad $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        config(['media-library.disk_name' => 'ftp_documentos']);

        Storage::fake('ftp_documentos');
        Storage::fake(AlmacenDeArchivos::DISCO_CACHE);

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        $this->otraEmpresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Norte']);

        Tenancy::usar($this->empresa);

        $this->unidad = Unidad::factory()->publicada()->create([
            'estado' => EstadoUnidad::Publicada,
            'precio_lista' => 120000,
        ]);
    }

    protected function agregar(string $coleccion): Media
    {
        return $this->unidad
            ->addMediaFromString('contenido de prueba')
            ->usingFileName(str($coleccion)->slug().'.pdf')
            ->toMediaCollection($coleccion);
    }

    protected function usuarioDe(Empresa $empresa): User
    {
        $usuario = User::factory()->create();
        $usuario->empresas()->attach($empresa);

        return $usuario;
    }

    public function test_el_archivo_se_guarda_en_el_disco_configurado(): void
    {
        $media = $this->agregar('documentos');

        Storage::disk('ftp_documentos')->assertExists(AlmacenDeArchivos::rutaDe($media));
        Storage::disk('public')->assertMissing(AlmacenDeArchivos::rutaDe($media));
    }

    /**
     * El FTP se comparte con otros sistemas de la DGT, que tienen ahí sus
     * carpetas PERMISO_TEMPORAL y compañía. Lo de Lotea tiene que poder
     * abrirse con un cliente de FTP y entenderse.
     */
    public function test_los_archivos_se_ordenan_por_concesionario_y_unidad(): void
    {
        $documento = $this->agregar('documentos');

        $this->assertSame(
            "autos-del-valle/unidades/{$this->unidad->id}/documentos/{$documento->getKey()}/documentos.pdf",
            AlmacenDeArchivos::rutaDe($documento),
        );
    }

    public function test_las_conversiones_van_junto_a_su_original(): void
    {
        $foto = $this->unidad->getFirstMedia('fotos');

        $this->assertStringStartsWith(
            "autos-del-valle/unidades/{$this->unidad->id}/fotos/{$foto->getKey()}/conversions/",
            AlmacenDeArchivos::rutaDe($foto, 'web'),
        );
    }

    /** Cada concesionario en su carpeta: si uno se va, se borra la suya y ya. */
    public function test_cada_concesionario_tiene_su_propia_carpeta(): void
    {
        $ajena = Tenancy::comoEmpresa($this->otraEmpresa, function () {
            $unidad = Unidad::factory()->create();

            return $unidad->addMediaFromString('otra')->usingFileName('x.pdf')->toMediaCollection('documentos');
        });

        $propio = $this->agregar('documentos');

        $this->assertStringStartsWith('autos-del-valle/', AlmacenDeArchivos::rutaDe($propio));
        $this->assertStringStartsWith('autos-del-norte/', AlmacenDeArchivos::rutaDe($ajena));
    }

    public function test_la_url_de_una_foto_apunta_a_la_ruta_que_la_sirve(): void
    {
        $foto = $this->unidad->getFirstMedia('fotos');

        $this->assertSame("/archivo/{$foto->getKey()}", $foto->getUrl());
        $this->assertSame("/archivo/{$foto->getKey()}/web", $foto->getUrl('web'));
    }

    public function test_cualquiera_ve_las_fotos_de_una_unidad_publicada(): void
    {
        $foto = $this->unidad->getFirstMedia('fotos');

        $this->get($foto->getUrl())->assertSuccessful();
    }

    /** El catálogo es lo que se vende; el navegador no tiene que volver a pedirlo. */
    public function test_las_fotos_publicas_se_sirven_con_cache_larga(): void
    {
        $foto = $this->unidad->getFirstMedia('fotos');

        $respuesta = $this->get($foto->getUrl());

        $this->assertStringContainsString('max-age=31536000', $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', $respuesta->headers->get('Cache-Control'));
    }

    /**
     * Antes esto era una URL pública. El título de un carro y su tarjeta de
     * circulación traen el nombre y el NIT del dueño.
     */
    public function test_un_desconocido_no_puede_abrir_un_documento(): void
    {
        $documento = $this->agregar('documentos');

        $this->get($documento->getUrl())->assertForbidden();
    }

    /** Son la prueba de cómo venía el carro: no van en el catálogo. */
    public function test_un_desconocido_no_ve_las_fotos_de_subasta(): void
    {
        $foto = $this->agregar('fotos_subasta');

        $this->get($foto->getUrl())->assertForbidden();
    }

    public function test_un_desconocido_no_ve_las_fotos_de_lo_que_no_esta_publicado(): void
    {
        $this->unidad->update(['publicado' => false]);

        $foto = $this->unidad->getFirstMedia('fotos');

        $this->get($foto->getUrl())->assertForbidden();
    }

    public function test_la_gente_del_concesionario_si_abre_sus_documentos(): void
    {
        $documento = $this->agregar('documentos');

        $this->actingAs($this->usuarioDe($this->empresa))
            ->get($documento->getUrl())
            ->assertSuccessful();
    }

    /** El aislamiento, otra vez: los papeles de un cliente son de ese cliente. */
    public function test_otro_concesionario_no_abre_los_documentos_ajenos(): void
    {
        $documento = $this->agregar('documentos');

        $this->actingAs($this->usuarioDe($this->otraEmpresa))
            ->get($documento->getUrl())
            ->assertForbidden();
    }

    public function test_los_documentos_no_se_cachean_en_el_navegador(): void
    {
        $documento = $this->agregar('documentos');

        $respuesta = $this->actingAs($this->usuarioDe($this->empresa))->get($documento->getUrl());

        $this->assertStringContainsString('private', $respuesta->headers->get('Cache-Control'));
    }

    /**
     * La copia local es lo que hace viable el portal: sin ella, veinte carros
     * con tres fotos serían sesenta lecturas del FTP por visitante.
     *
     * Se comprueba borrando el archivo del origen después de la primera
     * lectura: si la segunda sigue funcionando, salió de la copia.
     */
    public function test_la_primera_lectura_deja_copia_local_y_la_segunda_ya_no_toca_el_origen(): void
    {
        $foto = $this->unidad->getFirstMedia('fotos');
        $ruta = AlmacenDeArchivos::rutaDe($foto);

        Storage::disk(AlmacenDeArchivos::DISCO_CACHE)->assertMissing($ruta);

        $this->get($foto->getUrl())->assertSuccessful();

        Storage::disk(AlmacenDeArchivos::DISCO_CACHE)->assertExists($ruta);

        Storage::disk('ftp_documentos')->delete($ruta);

        $this->get($foto->getUrl())->assertSuccessful();
    }

    public function test_olvidar_la_copia_obliga_a_volver_a_bajarla(): void
    {
        $foto = $this->unidad->getFirstMedia('fotos');
        $ruta = AlmacenDeArchivos::rutaDe($foto);

        $this->get($foto->getUrl())->assertSuccessful();

        AlmacenDeArchivos::olvidarCache($foto);

        Storage::disk(AlmacenDeArchivos::DISCO_CACHE)->assertMissing($ruta);

        $this->get($foto->getUrl())->assertSuccessful();

        Storage::disk(AlmacenDeArchivos::DISCO_CACHE)->assertExists($ruta);
    }

    /** Alguien limpió el FTP a mano: es un 404, no un error del sistema. */
    public function test_un_archivo_que_ya_no_esta_en_el_disco_da_404(): void
    {
        $foto = $this->unidad->getFirstMedia('fotos');

        Storage::disk('ftp_documentos')->delete(AlmacenDeArchivos::rutaDe($foto));

        $this->get($foto->getUrl())->assertNotFound();
    }

    /** Si no, el disco se llena de fotos de carros que ya no existen. */
    public function test_borrar_una_foto_borra_su_copia_local(): void
    {
        $foto = $this->unidad->getFirstMedia('fotos');
        $ruta = AlmacenDeArchivos::rutaDe($foto);

        $this->get($foto->getUrl())->assertSuccessful();

        Storage::disk(AlmacenDeArchivos::DISCO_CACHE)->assertExists($ruta);

        $foto->delete();

        Storage::disk(AlmacenDeArchivos::DISCO_CACHE)->assertMissing($ruta);
    }

    public function test_el_logo_del_concesionario_tambien_vive_en_el_disco(): void
    {
        $ruta = UploadedFile::fake()->image('logo.png', 240, 80)->store('marcas', 'ftp_documentos');

        $this->empresa->update(['logo_path' => $ruta]);

        Storage::disk('ftp_documentos')->assertExists($ruta);

        // Se sirve como «logo-original» porque es el archivo que subió el
        // cliente; «logo» queda para la variante adaptada a fondos claros. Y
        // lleva un sello de versión para que cambiar el logo se note pese a la
        // caché del navegador.
        $this->assertStringStartsWith(
            "/marca/{$this->empresa->slug}/logo-original?v=",
            $this->empresa->fresh()->logo_url,
        );
    }

    public function test_el_logo_se_sirve_a_cualquiera(): void
    {
        $ruta = UploadedFile::fake()->image('logo.png', 240, 80)->store('marcas', 'ftp_documentos');
        $this->empresa->update(['logo_path' => $ruta]);

        $this->get($this->empresa->fresh()->logo_url)->assertSuccessful();
    }

    /**
     * Si el archivo no está, la URL se arma igual y la petición da 404.
     *
     * Antes se comprobaba en el disco antes de escribir la URL, y devolvía
     * null. Se quitó a conciencia: esa comprobación es un viaje de red, contra
     * R2 cuesta unos 300 ms, y el encabezado del portal pide el logo y el icono
     * en cada visita. Era más de un segundo de espera por página para evitar
     * una imagen rota en un caso que solo ocurre si alguien borra el archivo
     * por fuera del sistema.
     *
     * El camino guardado es la fuente de verdad: si está, el archivo se subió.
     * Quien sirve el archivo sí comprueba, así que no hay error, solo un 404.
     */
    public function test_un_logo_que_no_esta_en_el_disco_da_url_pero_no_archivo(): void
    {
        $this->empresa->update(['logo_path' => 'marcas/borrado.png']);

        $this->assertNotNull($this->empresa->fresh()->logo_url);
        $this->get("/marca/{$this->empresa->slug}/logo")->assertNotFound();
    }

    public function test_no_se_puede_pedir_un_tipo_de_marca_inventado(): void
    {
        $this->get("/marca/{$this->empresa->slug}/pasaporte")->assertNotFound();
    }

    public function test_no_se_puede_pedir_una_conversion_inventada(): void
    {
        $foto = $this->unidad->getFirstMedia('fotos');

        $this->get("/archivo/{$foto->getKey()}/../../etc/passwd")->assertNotFound();
        $this->get("/archivo/{$foto->getKey()}/loquesea")->assertNotFound();
    }
}
