<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Actions\RegistrarLecturaIa;
use App\Models\Empresa;
use App\Models\LecturaIa;
use App\Models\Plan;
use App\Support\TarifaDeIa;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La lectura con IA se vende aparte y se paga por uso.
 *
 * Sin medir el consumo el add-on se vende a ciegas: no se sabe si el precio
 * que se le cobra al cliente deja margen ni cuál cliente está comiéndose el
 * crédito.
 */
class AddOnDeIaTest extends TestCase
{
    use RefreshDatabase;

    protected function empresaCon(array $modulos, ?int $tope = null): Empresa
    {
        $plan = Plan::factory()->create([
            'modulos' => $modulos,
            'max_lecturas_ia' => $tope,
        ]);

        $empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Cliente '.uniqid(), 'plan_id' => $plan->id]);

        Tenancy::usar($empresa);

        return $empresa;
    }

    public function test_un_cliente_sin_el_modulo_no_puede_usar_la_ia(): void
    {
        $empresa = $this->empresaCon(['unidades', 'costeo']);

        $this->assertFalse($empresa->tieneModulo('ia'));
        $this->assertFalse($empresa->puedeLeerConIa());
    }

    public function test_un_cliente_con_el_modulo_si_puede(): void
    {
        $empresa = $this->empresaCon(['unidades', 'ia']);

        $this->assertTrue($empresa->tieneModulo('ia'));
        $this->assertTrue($empresa->puedeLeerConIa());
    }

    /** El tope protege el crédito de un cliente desbocado. */
    public function test_al_llegar_al_tope_del_mes_se_corta(): void
    {
        $empresa = $this->empresaCon(['ia'], tope: 3);
        $registrar = app(RegistrarLecturaIa::class);

        foreach (range(1, 3) as $ignorado) {
            $registrar->exitosa($this->consumo(), 8);
        }

        $this->assertSame(3, $empresa->lecturasIaDelMes());
        $this->assertFalse($empresa->fresh()->puedeLeerConIa());
    }

    public function test_sin_tope_no_se_corta_nunca(): void
    {
        $empresa = $this->empresaCon(['ia'], tope: null);

        foreach (range(1, 20) as $ignorado) {
            app(RegistrarLecturaIa::class)->exitosa($this->consumo(), 8);
        }

        $this->assertTrue($empresa->fresh()->puedeLeerConIa());
    }

    /** Las fallidas no gastan cupo: el cliente no pidió que fallara. */
    public function test_una_lectura_fallida_no_consume_cupo(): void
    {
        $empresa = $this->empresaCon(['ia'], tope: 2);

        app(RegistrarLecturaIa::class)->fallida('El servicio no respondió');

        $this->assertSame(0, $empresa->lecturasIaDelMes());
        $this->assertTrue($empresa->fresh()->puedeLeerConIa());
        $this->assertSame(1, LecturaIa::count());
    }

    public function test_el_costo_sale_de_los_tokens_que_reporta_openrouter(): void
    {
        config([
            'services.openrouter.precio_entrada' => 0.25,
            'services.openrouter.precio_salida' => 0.75,
        ]);

        // 2,700 de entrada y 250 de salida: una lectura de un documento.
        $esperado = (2700 / 1_000_000) * 0.25 + (250 / 1_000_000) * 0.75;

        $this->assertEqualsWithDelta($esperado, TarifaDeIa::costo(2700, 250), 0.0000001);
    }

    public function test_cada_lectura_queda_registrada_con_su_costo(): void
    {
        $empresa = $this->empresaCon(['ia']);

        app(RegistrarLecturaIa::class)->exitosa($this->consumo(), 11);

        $lectura = LecturaIa::first();

        $this->assertSame($empresa->id, $lectura->empresa_id);
        $this->assertSame(2700, $lectura->tokens_entrada);
        $this->assertSame(11, $lectura->campos_leidos);
        $this->assertGreaterThan(0, (float) $lectura->costo_usd);
        $this->assertEquals(TarifaDeIa::costo(2700, 250), $lectura->costo_usd);
    }

    public function test_el_consumo_del_mes_se_puede_sumar_por_cliente(): void
    {
        $empresa = $this->empresaCon(['ia']);

        foreach (range(1, 5) as $ignorado) {
            app(RegistrarLecturaIa::class)->exitosa($this->consumo(), 8);
        }

        $this->assertSame(5, $empresa->lecturasIaDelMes());
        $this->assertEqualsWithDelta(TarifaDeIa::costo(2700, 250) * 5, $empresa->costoIaDelMes(), 0.000001);
    }

    public function test_el_consumo_de_un_cliente_no_se_ve_desde_otro(): void
    {
        $primero = $this->empresaCon(['ia']);
        app(RegistrarLecturaIa::class)->exitosa($this->consumo(), 8);

        $segundo = $this->empresaCon(['ia']);

        $this->assertSame(0, $segundo->lecturasIaDelMes());
        $this->assertSame(1, $primero->lecturasIaDelMes());
    }

    /** @return array{tokens_entrada: int, tokens_salida: int, documentos: int, modelo: string} */
    protected function consumo(): array
    {
        return [
            'tokens_entrada' => 2700,
            'tokens_salida' => 250,
            'documentos' => 1,
            'modelo' => 'qwen/qwen2.5-vl-72b-instruct',
        ];
    }
}
