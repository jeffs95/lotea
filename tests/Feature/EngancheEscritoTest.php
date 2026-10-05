<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
use App\Models\Empresa;
use App\Models\Unidad;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El enganche se puede escribir, no solo deslizar.
 *
 * Quien pregunta por un carro no piensa «el treinta por ciento», piensa
 * «tengo quince mil ahorrados». Con la barra sola tenía que ir tanteando
 * hasta que el monto mostrado se pareciera al suyo.
 *
 * La cuenta vive en el navegador, así que lo que se puede comprobar desde
 * aquí es que la pieza esté servida y con el precio correcto. Es poco, pero
 * es lo que evita que el campo desaparezca en un cambio de plantilla y que
 * nadie lo note hasta que un cliente lo reclame.
 */
class EngancheEscritoTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        Tenancy::usar($this->empresa);
    }

    protected function html(array $datos = []): string
    {
        $unidad = Unidad::factory()->publicada()->create([
            'estado' => EstadoUnidad::Publicada,
            'publicado' => true,
            'precio_lista' => 85000,
            ...$datos,
        ]);

        return $this->get("/v/{$this->empresa->slug}/vehiculos/{$unidad->slug}")
            ->assertSuccessful()
            ->getContent();
    }

    public function test_la_calculadora_trae_el_campo_para_escribir_el_monto(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('data-enganche-monto', $html);
        $this->assertStringContainsString('data-calculadora', $html);
        $this->assertStringContainsString('data-precio="85000"', $html);
    }

    /**
     * La barra llega al 100 y va de uno en uno: con saltos de cinco, un
     * enganche escrito a mano dejaba la barra en un sitio y el porcentaje
     * diciendo otro.
     */
    public function test_la_barra_acompana_a_cualquier_monto(): void
    {
        $html = $this->html();

        $this->assertMatchesRegularExpression(
            '/<input[^>]*data-enganche[^>]*max="100"[^>]*step="1"/',
            $html,
        );
    }

    /** Si hay oferta, lo que se financia es el precio rebajado. */
    public function test_el_calculo_parte_del_precio_que_se_cobra(): void
    {
        $html = $this->html([
            'precio_oferta' => 72000,
            'oferta_hasta' => today()->addWeek(),
        ]);

        $this->assertStringContainsString('data-precio="72000"', $html);
    }
}
