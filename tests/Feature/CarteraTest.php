<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Actions\GenerarPlanPago;
use App\Actions\RegistrarPagoCuota;
use App\Actions\RegistrarVenta;
use App\Enums\EstadoUnidad;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\MovimientoCaja;
use App\Models\PlanPago;
use App\Models\Unidad;
use App\Models\Venta;
use App\Support\Tenancy;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La cartera es plata que se cobra durante años. Si la amortización no cierra
 * exacta, el cliente termina debiendo o pagando de más sin que nadie lo note.
 */
class CarteraTest extends TestCase
{
    use RefreshDatabase;

    protected Venta $venta;

    protected Caja $caja;

    protected function setUp(): void
    {
        parent::setUp();

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']));

        $unidad = Unidad::factory()->create(['estado' => EstadoUnidad::Publicada, 'precio_lista' => 120000]);

        $this->venta = app(RegistrarVenta::class)->ejecutar($unidad, [
            'cliente_id' => Cliente::factory()->create(['nombre' => 'María López'])->id,
            'estado' => 'cerrada',
            'precio_venta' => 120000,
            'forma_pago' => 'credito_propio',
            'enganche' => 30000,
        ]);

        $this->caja = Caja::factory()->create(['nombre' => 'Caja chica']);
    }

    protected function financiar(array $datos = []): PlanPago
    {
        return app(GenerarPlanPago::class)->ejecutar($this->venta, [
            'plazo_meses' => 12,
            'tasa_anual' => 18,
            ...$datos,
        ]);
    }

    public function test_el_plan_financia_lo_que_queda_despues_del_enganche(): void
    {
        $plan = $this->financiar();

        $this->assertEquals(90000, $plan->monto_financiado);
        $this->assertEquals(30000, $plan->enganche);
        $this->assertSame(12, $plan->cuotas()->count());
        $this->assertSame('CR-0001', $plan->numero);
    }

    /** La suma del capital de todas las cuotas es exactamente lo financiado. */
    public function test_la_amortizacion_cierra_exacta(): void
    {
        $plan = $this->financiar();

        $this->assertEquals(90000, $plan->cuotas()->sum('capital'));
        $this->assertEquals(0, $plan->cuotas()->reorder('numero', 'desc')->first()->saldo_despues);
    }

    public function test_sin_interes_las_cuotas_son_el_capital_dividido_en_el_plazo(): void
    {
        $plan = $this->financiar(['tasa_anual' => 0, 'plazo_meses' => 10]);

        $this->assertEquals(9000, $plan->cuota_mensual);
        $this->assertEquals(0, $plan->cuotas()->sum('interes'));
        $this->assertEquals(90000, $plan->cuotas()->sum('capital'));
    }

    public function test_el_interes_baja_y_el_capital_sube_con_cada_cuota(): void
    {
        $plan = $this->financiar();

        $primera = $plan->cuotas()->first();
        $ultima = $plan->cuotas()->reorder('numero', 'desc')->first();

        $this->assertGreaterThan((float) $ultima->interes, (float) $primera->interes);
        $this->assertLessThan((float) $ultima->capital, (float) $primera->capital);
    }

    public function test_las_cuotas_vencen_mes_a_mes(): void
    {
        $plan = $this->financiar(['primera_cuota' => '2026-09-15']);

        $this->assertSame('2026-09-15', $plan->cuotas()->first()->vence_en->toDateString());
        $this->assertSame('2026-10-15', $plan->cuotas()->skip(1)->first()->vence_en->toDateString());
    }

    public function test_no_se_puede_financiar_dos_veces_la_misma_venta(): void
    {
        $this->financiar();

        $this->expectException(DomainException::class);

        $this->financiar();
    }

    public function test_no_se_financia_si_el_enganche_cubre_todo(): void
    {
        $this->expectException(DomainException::class);

        $this->financiar(['enganche' => 120000]);
    }

    public function test_pagar_una_cuota_la_marca_pagada_y_entra_a_caja(): void
    {
        $plan = $this->financiar();
        $cuota = $plan->cuotas()->first();

        app(RegistrarPagoCuota::class)->ejecutar($cuota, [
            'monto' => $cuota->total,
            'metodo' => 'Efectivo',
        ], $this->caja);

        $cuota->refresh();

        $this->assertTrue($cuota->estaPagada());
        $this->assertEquals($cuota->total, $cuota->pagado);
        $this->assertEquals($cuota->total, $this->caja->fresh()->saldo);

        $movimiento = MovimientoCaja::first();
        $this->assertSame('cuota', $movimiento->categoria);
        $this->assertSame(Cuota::class, $movimiento->origen_type);
    }

    public function test_un_abono_parcial_deja_la_cuota_a_medias(): void
    {
        $plan = $this->financiar();
        $cuota = $plan->cuotas()->first();

        app(RegistrarPagoCuota::class)->ejecutar($cuota, ['monto' => 3000], $this->caja);

        $cuota->refresh();

        $this->assertSame('parcial', $cuota->estado);
        $this->assertFalse($cuota->estaPagada());
        $this->assertEquals(bcsub((string) $cuota->total, '3000.00', 2), $cuota->pendiente);
    }

    public function test_no_se_puede_abonar_mas_de_lo_que_debe_la_cuota(): void
    {
        $plan = $this->financiar();

        $this->expectException(DomainException::class);

        app(RegistrarPagoCuota::class)->ejecutar($plan->cuotas()->first(), ['monto' => 999999], $this->caja);
    }

    public function test_al_pagar_la_ultima_cuota_el_credito_queda_cancelado(): void
    {
        $plan = $this->financiar(['plazo_meses' => 2, 'tasa_anual' => 0]);
        $accion = app(RegistrarPagoCuota::class);

        foreach ($plan->cuotas as $cuota) {
            $accion->ejecutar($cuota, ['monto' => $cuota->total], $this->caja);
        }

        $this->assertSame('cancelado', $plan->fresh()->estado);
        $this->assertEquals(0, $plan->fresh()->saldo);
    }

    public function test_una_cuota_vencida_genera_mora_proporcional_a_los_dias(): void
    {
        $plan = $this->financiar(['primera_cuota' => now()->subDays(30)->toDateString(), 'tasa_mora_anual' => 36.5]);
        $cuota = $plan->cuotas()->first();

        $this->assertTrue($cuota->estaVencida());
        $this->assertEqualsWithDelta(30, $cuota->dias_de_atraso, 1);

        // 36.5% anual son 0.1% diario: 30 días sobre la cuota pendiente.
        $esperado = (float) $cuota->pendiente * 0.001 * 30;

        $this->assertEqualsWithDelta($esperado, (float) $cuota->moraAlDia(), 1);
    }

    public function test_una_cuota_al_dia_no_tiene_mora(): void
    {
        $plan = $this->financiar(['primera_cuota' => now()->addMonth()->toDateString(), 'tasa_mora_anual' => 36.5]);

        $this->assertEquals(0, $plan->cuotas()->first()->moraAlDia());
    }

    public function test_el_plan_reporta_su_mora_y_su_saldo(): void
    {
        $plan = $this->financiar(['primera_cuota' => now()->subMonths(2)->toDateString()]);

        $this->assertTrue($plan->estaEnMora());
        // Dos vencidas: la tercera vence hoy y todavía está a tiempo.
        $this->assertCount(2, $plan->cuotasVencidas());
        $this->assertEquals($plan->cuotas()->sum('total'), $plan->saldo);
    }

    public function test_la_cartera_de_una_empresa_no_se_ve_desde_otra(): void
    {
        $this->financiar();

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Zona 11']));

        $this->assertSame(0, PlanPago::count());
        $this->assertSame(0, Cuota::count());
    }
}
