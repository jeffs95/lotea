<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\Empresa;
use App\Models\Unidad;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las rebajas de precio.
 *
 * Un carro parado cuesta dinero todos los días, así que a las dos o tres
 * semanas la pregunta deja de ser cuánto vale y pasa a ser cuánto hay que
 * bajarle para que salga.
 *
 * Lo que se cuida: que el precio de lista no se pierda —la comparación entre
 * el de antes y el de ahora es lo que vende—, y sobre todo que no quede ningún
 * sitio mostrando el precio viejo. Anunciar una rebaja y cobrar otra cosa es
 * peor que no haberla hecho.
 */
class OfertasTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        Tenancy::usar($this->empresa);
    }

    protected function unidadRebajada(array $cambios = []): Unidad
    {
        return Unidad::factory()->publicada()->create([
            'precio_lista' => 70000,
            'precio_minimo' => 62000,
            'precio_oferta' => 59500,
            ...$cambios,
        ]);
    }

    // ── La cuenta ───────────────────────────────────────────────────────────

    public function test_calcula_el_ahorro_y_el_porcentaje(): void
    {
        $unidad = $this->unidadRebajada();

        $this->assertTrue($unidad->tieneOferta());
        $this->assertSame('10500.00', $unidad->ahorro);
        $this->assertSame(15, $unidad->descuento_porcentaje);
        $this->assertSame('59500.00', $unidad->precio_vigente);
    }

    public function test_sin_oferta_el_precio_vigente_es_el_de_lista(): void
    {
        $unidad = Unidad::factory()->publicada()->create(['precio_lista' => 70000]);

        $this->assertFalse($unidad->tieneOferta());
        $this->assertSame('70000.00', $unidad->precio_vigente);
        $this->assertNull($unidad->ahorro);
    }

    /** Una «oferta» que no baja el precio no es una oferta. */
    public function test_una_oferta_igual_o_mayor_que_el_precio_no_cuenta(): void
    {
        $this->assertFalse($this->unidadRebajada(['precio_oferta' => 70000])->tieneOferta());
        $this->assertFalse($this->unidadRebajada(['precio_oferta' => 80000])->tieneOferta());
    }

    // ── La vigencia ─────────────────────────────────────────────────────────

    /** Sin fecha, dura hasta que la quiten. */
    public function test_sin_fecha_la_oferta_sigue_vigente(): void
    {
        $this->assertTrue($this->unidadRebajada(['oferta_hasta' => null])->tieneOferta());
    }

    public function test_una_oferta_vencida_deja_de_aplicar(): void
    {
        $unidad = $this->unidadRebajada(['oferta_hasta' => today()->subDay()]);

        $this->assertFalse($unidad->tieneOferta());
        $this->assertSame('70000.00', $unidad->precio_vigente, 'Vencida, pero sigue cobrando el precio rebajado.');
    }

    public function test_el_ultimo_dia_todavia_vale(): void
    {
        $this->assertTrue($this->unidadRebajada(['oferta_hasta' => today()])->tieneOferta());
    }

    // ── La protección del margen ────────────────────────────────────────────

    /**
     * No se impide bajar del piso: para un carro que lleva meses parado, a
     * veces liquidar es la decisión correcta. Pero tiene que poder detectarse.
     */
    public function test_avisa_cuando_la_rebaja_se_come_el_minimo(): void
    {
        $this->assertTrue($this->unidadRebajada(['precio_oferta' => 59500])->ofertaBajaDelMinimo());
        $this->assertFalse($this->unidadRebajada(['precio_oferta' => 65000])->ofertaBajaDelMinimo());
    }

    // ── El portal ───────────────────────────────────────────────────────────

    public function test_el_catalogo_muestra_los_dos_precios(): void
    {
        $this->unidadRebajada();

        $html = $this->get("/v/{$this->empresa->slug}/vehiculos")->assertSuccessful()->getContent();

        $this->assertStringContainsString('59,500', $html, 'No se ve el precio rebajado.');
        $this->assertStringContainsString('70,000', $html, 'No se ve el precio de antes para comparar.');
        $this->assertStringContainsString('line-through', $html, 'El precio viejo no está tachado.');
    }

    public function test_el_distintivo_lleva_el_porcentaje(): void
    {
        $this->unidadRebajada(['oferta_etiqueta' => 'Candente']);

        $this->get("/v/{$this->empresa->slug}/vehiculos")
            ->assertSee('Candente')
            ->assertSee('15%');
    }

    /**
     * El que de verdad importa: la cuota que calcula el visitante y el precio
     * que lee Google tienen que ser el rebajado. Si se quedan con el de lista,
     * se anuncia una rebaja y se cobra otra cosa.
     */
    public function test_la_cuota_y_el_dato_para_google_usan_el_precio_rebajado(): void
    {
        $unidad = $this->unidadRebajada();

        $html = $this->get("/v/{$this->empresa->slug}/vehiculos/{$unidad->slug}")
            ->assertSuccessful()
            ->getContent();

        $this->assertStringContainsString('data-precio="59500"', $html, 'La calculadora de cuota usa el precio viejo.');
        $this->assertStringContainsString('"price":59500', $html, 'A Google se le anuncia el precio viejo.');
    }

    public function test_una_oferta_vencida_no_aparece_rebajada_en_el_portal(): void
    {
        $this->unidadRebajada(['oferta_hasta' => today()->subWeek()]);

        $html = $this->get("/v/{$this->empresa->slug}/vehiculos")->getContent();

        $this->assertStringNotContainsString('59,500', $html);
        $this->assertStringContainsString('70,000', $html);
    }

    // ── El aviso al entrar ──────────────────────────────────────────────────

    public function test_el_aviso_sale_cuando_hay_rebajas(): void
    {
        $this->unidadRebajada();

        $this->get("/v/{$this->empresa->slug}")
            ->assertSuccessful()
            ->assertSee('data-aviso-ofertas', false)
            ->assertSee('59,500');
    }

    /** Sin rebajas no hay aviso: nadie quiere un modal vacío al entrar. */
    public function test_sin_rebajas_no_hay_aviso(): void
    {
        Unidad::factory()->publicada()->create(['precio_lista' => 70000]);

        $this->get("/v/{$this->empresa->slug}")
            ->assertSuccessful()
            ->assertDontSee('data-aviso-ofertas', false);
    }

    /** Ni con una rebaja vencida. */
    public function test_una_oferta_vencida_no_dispara_el_aviso(): void
    {
        $this->unidadRebajada(['oferta_hasta' => today()->subDay()]);

        $this->get("/v/{$this->empresa->slug}")->assertDontSee('data-aviso-ofertas', false);
    }

    /** Una rebaja de un carro sin publicar no se anuncia a nadie. */
    public function test_lo_que_no_esta_publicado_no_sale_en_el_aviso(): void
    {
        Unidad::factory()->create([
            'publicado' => false,
            'precio_lista' => 70000,
            'precio_oferta' => 50000,
        ]);

        $this->get("/v/{$this->empresa->slug}")->assertDontSee('data-aviso-ofertas', false);
    }

    /** Y no se cuela la rebaja de otro concesionario. */
    public function test_el_aviso_no_mezcla_clientes(): void
    {
        $this->unidadRebajada(['precio_oferta' => 59500]);

        $otra = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Norte']);
        Tenancy::comoEmpresa($otra, fn () => Unidad::factory()->publicada()->create([
            'precio_lista' => 40000,
            'precio_oferta' => 31111,
        ]));

        $this->get("/v/{$this->empresa->slug}")
            ->assertSee('59,500')
            ->assertDontSee('31,111');
    }
}
