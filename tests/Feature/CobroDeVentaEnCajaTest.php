<?php

namespace Tests\Feature;

use App\Actions\AnularVenta;
use App\Actions\CobrarVentaEnCaja;
use App\Actions\CrearEmpresa;
use App\Actions\RegistrarVenta;
use App\Enums\EstadoUnidad;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\MovimientoCaja;
use App\Models\Unidad;
use App\Models\Venta;
use App\Support\Tenancy;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El puente entre vender un carro y que el dinero aparezca en la caja.
 *
 * Si esto falla, el dueño cierra una venta y al arquear la caja no encuentra
 * el efectivo, o lo encuentra suelto sin poder decir de qué carro salió.
 */
class CobroDeVentaEnCajaTest extends TestCase
{
    use RefreshDatabase;

    protected Caja $caja;

    protected Venta $venta;

    protected function setUp(): void
    {
        parent::setUp();

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']));

        $this->caja = Caja::factory()->create(['saldo_inicial' => 1000]);

        $unidad = Unidad::factory()->publicada()->create([
            'estado' => EstadoUnidad::Publicada,
            'precio_lista' => 148000,
        ]);

        $this->venta = app(RegistrarVenta::class)->ejecutar($unidad, [
            'cliente_id' => Cliente::factory()->create(['nombre' => 'María López'])->id,
            'estado' => 'cerrada',
            'precio_venta' => 145000,
        ]);
    }

    protected function cobrar(array $datos = []): MovimientoCaja
    {
        return app(CobrarVentaEnCaja::class)->ejecutar($this->venta, $this->caja, [
            'monto' => 145000,
            ...$datos,
        ]);
    }

    public function test_el_cobro_entra_a_la_caja_y_suma_al_saldo(): void
    {
        $this->cobrar();

        $this->assertSame('146000.00', (string) $this->caja->refresh()->saldo);
    }

    /**
     * Sin este enlace, dentro de tres meses nadie puede decir de qué carro
     * salió ese ingreso salvo leyendo la descripción y adivinando.
     */
    public function test_el_movimiento_queda_enlazado_a_la_venta(): void
    {
        $movimiento = $this->cobrar();

        $this->assertSame(Venta::class, $movimiento->origen_type);
        $this->assertSame($this->venta->id, $movimiento->origen_id);
        $this->assertSame($this->venta->numero, $movimiento->documento);
    }

    public function test_es_un_ingreso_con_categoria_de_venta(): void
    {
        $movimiento = $this->cobrar();

        $this->assertSame('ingreso', $movimiento->tipo);
        $this->assertSame('venta', $movimiento->categoria);
    }

    public function test_la_descripcion_dice_el_numero_de_venta_y_el_cliente(): void
    {
        $movimiento = $this->cobrar();

        $this->assertStringContainsString($this->venta->numero, $movimiento->descripcion);
        $this->assertStringContainsString('María López', $movimiento->descripcion);
    }

    public function test_el_enganche_se_distingue_del_cobro_completo(): void
    {
        $movimiento = $this->cobrar(['categoria' => 'enganche', 'monto' => 30000]);

        $this->assertSame('enganche', $movimiento->categoria);
        $this->assertStringContainsString('Enganche', $movimiento->descripcion);
    }

    public function test_una_descripcion_propia_pisa_la_automatica(): void
    {
        $movimiento = $this->cobrar(['descripcion' => 'Pago con cheque del banco']);

        $this->assertSame('Pago con cheque del banco', $movimiento->descripcion);
    }

    public function test_no_se_puede_cobrar_una_venta_anulada(): void
    {
        app(AnularVenta::class)->ejecutar($this->venta, 'El cliente no consiguió el crédito');

        $this->expectException(DomainException::class);

        $this->cobrar();
    }

    public function test_se_pueden_registrar_varios_abonos_de_la_misma_venta(): void
    {
        $this->cobrar(['categoria' => 'enganche', 'monto' => 30000]);
        $this->cobrar(['monto' => 115000]);

        $this->assertSame(2, $this->venta->refresh()->movimientosCaja()->count());
        $this->assertSame('146000.00', (string) $this->caja->refresh()->saldo);
    }
}
