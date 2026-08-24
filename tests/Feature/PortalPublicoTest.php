<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\Unidad;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El portal es la cara pública de cada concesionario. Dos cosas no pueden
 * fallar: que no se filtre inventario que no está publicado, y que el sitio de
 * un cliente jamás muestre carros de otro.
 */
class PortalPublicoTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar([
            'nombre' => 'Autos del Valle, S.A.',
            'nombre_comercial' => 'Autos del Valle',
            'slug' => 'autos-del-valle',
            'telefono' => '2222-3333',
        ]);
    }

    protected function publicar(array $atributos = []): Unidad
    {
        return Tenancy::comoEmpresa($this->empresa, fn () => Unidad::factory()->publicada()->create([
            'estado' => EstadoUnidad::Publicada,
            'precio_lista' => 148000,
            // Sin slug a propósito: que lo genere el modelo, como en la vida
            // real. Fijarlo aquí escondía que nadie lo generaba y el portal se
            // caía con un 500 en cuanto se publicaba un carro de verdad.
            ...$atributos,
        ]));
    }

    protected function url(string $ruta = ''): string
    {
        return "/v/{$this->empresa->slug}{$ruta}";
    }

    public function test_el_catalogo_muestra_las_unidades_publicadas(): void
    {
        $this->publicar(['stock_no' => 'PUB-1']);

        $this->get($this->url('/vehiculos'))
            ->assertOk()
            ->assertSee('PUB-1');
    }

    public function test_el_catalogo_no_muestra_lo_que_no_esta_publicado(): void
    {
        $this->publicar(['stock_no' => 'PUB-1']);

        Tenancy::comoEmpresa($this->empresa, fn () => Unidad::factory()->create([
            'stock_no' => 'RESERVADO-INTERNO',
            'publicado' => false,
            'estado' => EstadoUnidad::EnTaller,
        ]));

        $this->get($this->url('/vehiculos'))
            ->assertOk()
            ->assertSee('PUB-1')
            ->assertDontSee('RESERVADO-INTERNO');
    }

    public function test_el_portal_de_un_concesionario_no_muestra_carros_de_otro(): void
    {
        $this->publicar(['stock_no' => 'MIA-1']);

        $otra = (new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Zona 11']);

        Tenancy::comoEmpresa($otra, fn () => Unidad::factory()->publicada()->create([
            'stock_no' => 'AJENA-9',
            'estado' => EstadoUnidad::Publicada,
        ]));

        $this->get($this->url('/vehiculos'))
            ->assertOk()
            ->assertSee('MIA-1')
            ->assertDontSee('AJENA-9');
    }

    public function test_la_ficha_de_una_unidad_publicada_carga_con_su_schema(): void
    {
        $unidad = $this->publicar();

        $this->get($this->url("/vehiculos/{$unidad->slug}"))
            ->assertOk()
            ->assertSee($unidad->stock_no)
            ->assertSee('schema.org', escape: false)
            ->assertSee('"@type":"Car"', escape: false)
            ->assertSee('Calculá tu cuota', escape: false);
    }

    public function test_no_se_puede_abrir_la_ficha_de_una_unidad_sin_publicar(): void
    {
        $oculta = Tenancy::comoEmpresa($this->empresa, fn () => Unidad::factory()->create([
            'publicado' => false,
            'estado' => EstadoUnidad::EnTaller,
            'slug' => 'oculta',
        ]));

        $this->get($this->url("/vehiculos/{$oculta->slug}"))->assertNotFound();
    }

    /** La preventa: se publica desde que va en el barco. */
    public function test_una_unidad_en_camino_si_se_puede_publicar(): void
    {
        $enCamino = $this->publicar([
            'estado' => EstadoUnidad::Embarcada,
            'stock_no' => 'CAMINO-1',
            'slug' => 'en-camino',
        ]);

        $this->get($this->url("/vehiculos/{$enCamino->slug}"))
            ->assertOk()
            ->assertSee('Próximamente');
    }

    public function test_el_formulario_deja_el_prospecto_en_el_crm(): void
    {
        $unidad = $this->publicar();

        $this->post($this->url('/contacto'), [
            'nombre' => 'María López',
            'telefono' => '5555-1234',
            'email' => 'maria@example.gt',
            'mensaje' => '¿Sigue disponible?',
            'unidad_id' => $unidad->id,
        ])->assertRedirect();

        Tenancy::usar($this->empresa);

        $lead = Lead::first();

        $this->assertSame('María López', $lead->nombre);
        $this->assertSame($unidad->id, $lead->unidad_id);
        $this->assertSame('portal', $lead->origen);
        $this->assertSame('nuevo', $lead->estado);
        $this->assertTrue($lead->estaSinAtender());
    }

    /** Un id manipulado no puede colgar un lead de la unidad de otro cliente. */
    public function test_un_id_de_otra_empresa_no_cruza_datos(): void
    {
        $otra = (new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Zona 11']);
        $ajena = Tenancy::comoEmpresa($otra, fn () => Unidad::factory()->create());

        $this->post($this->url('/contacto'), [
            'nombre' => 'Curioso',
            'telefono' => '5555-0000',
            'unidad_id' => $ajena->id,
        ])->assertRedirect();

        Tenancy::usar($this->empresa);

        $this->assertNull(Lead::first()->unidad_id);
    }

    public function test_el_portal_de_una_empresa_desactivada_no_responde(): void
    {
        $this->empresa->update(['activa' => false]);

        $this->get($this->url('/vehiculos'))->assertNotFound();
    }

    public function test_una_empresa_que_no_existe_da_404(): void
    {
        $this->get('/v/no-existe/vehiculos')->assertNotFound();
    }

    /**
     * El slug es lo que arma la URL de cada carro. Sin él, la tarjeta del
     * catálogo revienta y se cae el portal entero con un 500: no es que falte
     * un carro, es que no carga la página.
     */
    public function test_publicar_una_unidad_le_da_su_slug(): void
    {
        $unidad = $this->publicar(['stock_no' => '0007']);

        $this->assertNotNull($unidad->fresh()->slug);
        $this->assertStringEndsWith('-0007', $unidad->fresh()->slug);
    }

    /** Ese enlace ya salió por WhatsApp: no puede cambiar bajo los pies. */
    public function test_el_slug_no_cambia_al_editar_la_unidad(): void
    {
        $unidad = $this->publicar();

        $original = $unidad->fresh()->slug;

        $unidad->update(['anio' => 2020, 'precio_lista' => 88000]);

        $this->assertSame($original, $unidad->fresh()->slug);
    }

    /** El caso que tumbó el portal de verdad. */
    public function test_el_catalogo_carga_con_una_unidad_recien_publicada(): void
    {
        $this->publicar();

        $this->get("/v/{$this->empresa->slug}")->assertSuccessful();
        $this->get("/v/{$this->empresa->slug}/vehiculos")->assertSuccessful();
    }
}
