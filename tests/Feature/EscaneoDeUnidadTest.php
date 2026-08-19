<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
use App\Models\Empresa;
use App\Models\Unidad;
use App\Models\User;
use App\Support\CodigoDeUnidad;
use App\Support\QrDeUnidad;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El QR del parabrisas: un solo código que lleva al cliente a la ficha pública
 * y al vendedor a la pantalla interna.
 */
class EscaneoDeUnidadTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected Unidad $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar([
            'nombre' => 'Autos del Valle',
            'slug' => 'autos-del-valle',
        ]);

        Tenancy::usar($this->empresa);

        $this->unidad = Unidad::factory()->create([
            'estado' => EstadoUnidad::Publicada,
            'publicado' => true,
            'slug' => 'toyota-rav4-2019-stock-1',
        ]);
    }

    public function test_cada_unidad_nace_con_su_codigo(): void
    {
        $this->assertNotNull($this->unidad->codigo_qr);
        $this->assertSame(CodigoDeUnidad::LARGO, strlen($this->unidad->codigo_qr));
    }

    /** El código se dicta por teléfono: nada de O contra 0 ni I contra 1. */
    public function test_el_codigo_no_usa_caracteres_que_se_confunden(): void
    {
        foreach (range(1, 30) as $ignorado) {
            $this->assertDoesNotMatchRegularExpression('/[OI15SAEU]/', CodigoDeUnidad::generar());
        }
    }

    public function test_dos_unidades_no_comparten_codigo(): void
    {
        $codigos = Unidad::factory()->count(20)->create()->pluck('codigo_qr');

        $this->assertCount(20, $codigos->unique());
    }

    public function test_un_cliente_cae_en_la_ficha_publica(): void
    {
        $this->get("/u/{$this->unidad->codigo_qr}")
            ->assertRedirect("/v/{$this->empresa->slug}/vehiculos/{$this->unidad->slug}");
    }

    public function test_el_codigo_funciona_aunque_se_escriba_en_minusculas(): void
    {
        $this->get('/u/'.strtolower($this->unidad->codigo_qr))
            ->assertRedirect("/v/{$this->empresa->slug}/vehiculos/{$this->unidad->slug}");
    }

    /** El mismo QR, otro destino: quien trabaja ahí entra a la ficha interna. */
    public function test_alguien_del_concesionario_cae_en_la_ficha_interna(): void
    {
        $usuario = User::factory()->create();
        $usuario->empresas()->attach($this->empresa);

        $respuesta = $this->actingAs($usuario)->get("/u/{$this->unidad->codigo_qr}");

        $respuesta->assertRedirectContains("/app/{$this->empresa->slug}/unidades/{$this->unidad->id}");
    }

    /** Alguien de otro concesionario es un cliente más: ficha pública. */
    public function test_un_usuario_de_otra_empresa_no_entra_a_la_ficha_interna(): void
    {
        $otra = (new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Zona 11']);

        $ajeno = User::factory()->create();
        $ajeno->empresas()->attach($otra);

        $this->actingAs($ajeno)
            ->get("/u/{$this->unidad->codigo_qr}")
            ->assertRedirect("/v/{$this->empresa->slug}/vehiculos/{$this->unidad->slug}");
    }

    public function test_una_unidad_sin_publicar_muestra_el_aviso_en_vez_de_un_404(): void
    {
        $oculta = Tenancy::comoEmpresa($this->empresa, fn () => Unidad::factory()->create([
            'publicado' => false,
            'estado' => EstadoUnidad::EnTaller,
        ]));

        $this->get("/u/{$oculta->codigo_qr}")
            ->assertOk()
            ->assertSee('todavía no está publicado', escape: false)
            ->assertSee($oculta->codigo_qr);
    }

    public function test_un_codigo_que_no_existe_da_404(): void
    {
        $this->get('/u/ZZZZZZ')->assertNotFound();
    }

    /** Si el concesionario está suspendido, su QR deja de funcionar. */
    public function test_el_qr_de_un_suspendido_no_responde(): void
    {
        $this->empresa->update(['suspendida_en' => now(), 'motivo_suspension' => 'No paga']);

        $this->get("/u/{$this->unidad->codigo_qr}")->assertNotFound();
    }

    public function test_el_qr_apunta_a_la_ruta_de_escaneo(): void
    {
        $this->assertStringContainsString("/u/{$this->unidad->codigo_qr}", QrDeUnidad::url($this->unidad));
        $this->assertStringStartsWith('data:image/svg+xml;base64,', QrDeUnidad::dataUri($this->unidad));
    }
}
