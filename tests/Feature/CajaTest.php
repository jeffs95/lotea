<?php

namespace Tests\Feature;

use App\Actions\AnularMovimientoCaja;
use App\Actions\ArquearCaja;
use App\Actions\CrearEmpresa;
use App\Actions\RegistrarMovimientoCaja;
use App\Actions\TrasladarEntreCajas;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\TipoCambio;
use App\Support\Tenancy;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Una caja que no cuadra no sirve para nada. Estas cuentas tienen que dar
 * exactas y los movimientos no pueden desaparecer.
 */
class CajaTest extends TestCase
{
    use RefreshDatabase;

    protected Caja $caja;

    protected function setUp(): void
    {
        parent::setUp();

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']));

        $this->caja = Caja::factory()->create(['saldo_inicial' => 1000]);
    }

    protected function mover(string $tipo, float $monto, ?Caja $caja = null): MovimientoCaja
    {
        return app(RegistrarMovimientoCaja::class)->ejecutar($caja ?? $this->caja, [
            'tipo' => $tipo,
            'monto' => $monto,
            'descripcion' => 'Movimiento de prueba',
        ]);
    }

    public function test_el_saldo_arranca_en_el_saldo_inicial(): void
    {
        $this->assertEquals(1000, $this->caja->saldo);
    }

    public function test_los_ingresos_suman_y_los_egresos_restan(): void
    {
        $this->mover('ingreso', 2500);
        $this->mover('egreso', 800);

        $this->assertEquals(2700, $this->caja->fresh()->saldo);
    }

    public function test_no_se_puede_registrar_un_monto_en_cero(): void
    {
        $this->expectException(DomainException::class);

        $this->mover('ingreso', 0);
    }

    public function test_anular_un_movimiento_lo_saca_del_saldo_pero_lo_deja_visible(): void
    {
        $movimiento = $this->mover('ingreso', 5000);

        $this->assertEquals(6000, $this->caja->fresh()->saldo);

        app(AnularMovimientoCaja::class)->ejecutar($movimiento, 'Se registró dos veces');

        $this->assertEquals(1000, $this->caja->fresh()->saldo);
        $this->assertSame(1, MovimientoCaja::count());
        $this->assertSame('Se registró dos veces', $movimiento->fresh()->motivo_anulacion);
    }

    public function test_no_se_puede_anular_sin_motivo(): void
    {
        $movimiento = $this->mover('ingreso', 100);

        $this->expectException(DomainException::class);

        app(AnularMovimientoCaja::class)->ejecutar($movimiento, '   ');
    }

    public function test_una_caja_en_dolares_guarda_su_equivalente_en_quetzales(): void
    {
        TipoCambio::create(['fecha' => now(), 'moneda' => 'USD', 'tasa' => 7.70]);

        $dolares = Caja::factory()->enDolares()->create();

        $movimiento = $this->mover('ingreso', 1000, $dolares);

        $this->assertSame('USD', $movimiento->moneda);
        $this->assertEquals(7.70, $movimiento->tipo_cambio);
        $this->assertEquals(7700, $movimiento->monto_base);
        $this->assertEquals(1000, $dolares->fresh()->saldo);
        $this->assertEquals(7700, $dolares->fresh()->saldo_en_quetzales);
    }

    public function test_un_traslado_mueve_el_dinero_de_una_caja_a_la_otra(): void
    {
        $destino = Caja::factory()->create(['nombre' => 'Cuenta banco', 'tipo' => 'banco']);

        app(TrasladarEntreCajas::class)->ejecutar($this->caja, $destino, ['monto' => 600]);

        $this->assertEquals(400, $this->caja->fresh()->saldo);
        $this->assertEquals(600, $destino->fresh()->saldo);
    }

    public function test_no_se_puede_trasladar_mas_de_lo_que_hay(): void
    {
        $destino = Caja::factory()->create(['nombre' => 'Cuenta banco']);

        $this->expectException(DomainException::class);

        app(TrasladarEntreCajas::class)->ejecutar($this->caja, $destino, ['monto' => 5000]);
    }

    public function test_no_se_puede_trasladar_entre_monedas_distintas(): void
    {
        $dolares = Caja::factory()->enDolares()->create();

        $this->expectException(DomainException::class);

        app(TrasladarEntreCajas::class)->ejecutar($this->caja, $dolares, ['monto' => 100]);
    }

    /** Anular media transferencia dejaría dinero apareciendo de la nada. */
    public function test_anular_un_traslado_deshace_las_dos_patas(): void
    {
        $destino = Caja::factory()->create(['nombre' => 'Cuenta banco']);

        $movimientos = app(TrasladarEntreCajas::class)->ejecutar($this->caja, $destino, ['monto' => 600]);

        app(AnularMovimientoCaja::class)->ejecutar($movimientos->first(), 'Traslado equivocado');

        $this->assertEquals(1000, $this->caja->fresh()->saldo);
        $this->assertEquals(0, $destino->fresh()->saldo);
        $this->assertTrue($movimientos->last()->fresh()->estaAnulado());
    }

    public function test_el_arqueo_deja_la_diferencia_registrada(): void
    {
        $this->mover('ingreso', 500);

        $arqueo = app(ArquearCaja::class)->ejecutar($this->caja, 1450, 'Faltaron Q50 de la gasolina del sábado');

        $this->assertEquals(1500, $arqueo->saldo_sistema);
        $this->assertEquals(1450, $arqueo->saldo_contado);
        $this->assertEquals(-50, $arqueo->diferencia);
        $this->assertTrue($arqueo->hayFaltante());
        $this->assertFalse($arqueo->cuadro());

        // El arqueo no toca el saldo: solo deja constancia.
        $this->assertEquals(1500, $this->caja->fresh()->saldo);
    }

    public function test_un_arqueo_que_cuadra_se_marca_como_tal(): void
    {
        $arqueo = app(ArquearCaja::class)->ejecutar($this->caja, 1000);

        $this->assertTrue($arqueo->cuadro());
        $this->assertFalse($arqueo->hayFaltante());
    }

    public function test_las_cajas_de_una_empresa_no_se_ven_desde_otra(): void
    {
        $this->mover('ingreso', 100);

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Zona 11']));

        $this->assertSame(0, Caja::count());
        $this->assertSame(0, MovimientoCaja::count());
    }
}
