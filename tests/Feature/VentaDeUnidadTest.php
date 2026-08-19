<?php

namespace Tests\Feature;

use App\Actions\AnularVenta;
use App\Actions\CrearEmpresa;
use App\Actions\RegistrarCosto;
use App\Actions\RegistrarVenta;
use App\Enums\EstadoUnidad;
use App\Models\CategoriaCosto;
use App\Models\Cliente;
use App\Models\CostoUnidad;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Support\Tenancy;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La venta es donde el margen deja de ser estimado. Si estas cuentas fallan,
 * el dueño cree que ganó lo que no ganó.
 */
class VentaDeUnidadTest extends TestCase
{
    use RefreshDatabase;

    protected Unidad $unidad;

    protected Cliente $cliente;

    protected User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']));

        $this->unidad = Unidad::factory()->publicada()->create([
            'estado' => EstadoUnidad::Publicada,
            'precio_lista' => 148000,
        ]);

        // Costo real de la unidad: Q86,000.
        app(RegistrarCosto::class)->ejecutar($this->unidad, [
            'categoria_costo_id' => CategoriaCosto::where('codigo', 'precio_compra')->value('id'),
            'monto' => 86000,
        ]);

        $this->cliente = Cliente::factory()->create(['nombre' => 'María López']);
        $this->vendedor = User::factory()->create(['name' => 'Carlos Vendedor']);
    }

    protected function vender(array $datos = []): Venta
    {
        return app(RegistrarVenta::class)->ejecutar($this->unidad, [
            'cliente_id' => $this->cliente->id,
            'vendedor_id' => $this->vendedor->id,
            'estado' => 'cerrada',
            'precio_venta' => 145000,
            ...$datos,
        ]);
    }

    public function test_cerrar_una_venta_marca_la_unidad_como_vendida(): void
    {
        $venta = $this->vender();

        $this->assertSame('cerrada', $venta->estado);
        $this->assertSame(EstadoUnidad::Vendida, $this->unidad->fresh()->estado);
        $this->assertNotNull($this->unidad->fresh()->fecha_venta);
        $this->assertFalse($this->unidad->fresh()->publicado);
    }

    public function test_el_descuento_baja_el_precio_final(): void
    {
        $venta = $this->vender(['precio_venta' => 148000, 'descuento' => 6000]);

        $this->assertEquals(142000, $venta->precio_final);
    }

    /** El margen real usa el precio de cierre, no el de lista. */
    public function test_el_margen_se_calcula_sobre_el_precio_real_de_venta(): void
    {
        $this->vender(['precio_venta' => 140000]);

        $unidad = $this->unidad->fresh();

        $this->assertEquals(140000, $unidad->precio_para_margen);
        $this->assertEquals(54000, $unidad->utilidad_estimada);
    }

    public function test_la_comision_se_calcula_sobre_la_utilidad(): void
    {
        // Utilidad: 145,000 - 86,000 = 59,000. El 5% son 2,950.
        $venta = $this->vender(['comision_porcentaje' => 5]);

        $this->assertEquals(2950, $venta->comision_monto);
    }

    public function test_la_comision_tambien_puede_ir_sobre_el_precio(): void
    {
        $venta = $this->vender(['comision_base' => 'precio', 'comision_porcentaje' => 1.5]);

        $this->assertEquals(2175, $venta->comision_monto);
    }

    /** Si el vendedor regala precio, se corta su propia comisión. */
    public function test_regalar_precio_reduce_la_comision(): void
    {
        $conDescuento = app(RegistrarVenta::class)->ejecutar($this->unidad, [
            'cliente_id' => $this->cliente->id,
            'vendedor_id' => $this->vendedor->id,
            'estado' => 'cerrada',
            'precio_venta' => 145000,
            'descuento' => 15000,
            'comision_porcentaje' => 5,
        ]);

        // Utilidad: 130,000 - 86,000 = 44,000. El 5% son 2,200 y no 2,950.
        $this->assertEquals(2200, $conDescuento->comision_monto);
    }

    public function test_la_comision_entra_como_gasto_que_no_encarece_el_carro(): void
    {
        $costoAntes = $this->unidad->fresh()->costo_total;

        $this->vender(['comision_porcentaje' => 5]);

        $comision = CostoUnidad::whereHas('categoria', fn ($q) => $q->where('codigo', 'comision_vendedor'))->first();

        $this->assertNotNull($comision);
        $this->assertEquals(2950, $comision->monto_base);
        $this->assertEquals($costoAntes, $this->unidad->fresh()->costo_total);
    }

    public function test_una_cotizacion_no_toca_la_unidad(): void
    {
        $venta = $this->vender(['estado' => 'cotizacion']);

        $this->assertSame('cotizacion', $venta->estado);
        $this->assertSame(EstadoUnidad::Publicada, $this->unidad->fresh()->estado);
        $this->assertEquals(0, $venta->comision_monto);
    }

    public function test_no_se_puede_vender_dos_veces_la_misma_unidad(): void
    {
        $this->vender();

        $this->expectException(DomainException::class);

        $this->vender();
    }

    public function test_anular_una_venta_devuelve_la_unidad_al_patio(): void
    {
        $venta = $this->vender(['comision_porcentaje' => 5]);

        app(AnularVenta::class)->ejecutar($venta, 'El banco no aprobó el crédito');

        $this->assertSame('anulada', $venta->fresh()->estado);
        $this->assertSame(EstadoUnidad::Lista, $this->unidad->fresh()->estado);
        $this->assertNull($this->unidad->fresh()->fecha_venta);

        $comision = CostoUnidad::whereHas('categoria', fn ($q) => $q->where('codigo', 'comision_vendedor'))->first();
        $this->assertNotNull($comision->anulado_en);
    }

    public function test_despues_de_anular_se_puede_volver_a_vender(): void
    {
        $primera = $this->vender();
        app(AnularVenta::class)->ejecutar($primera, 'Se arrepintió');

        $segunda = $this->vender(['precio_venta' => 150000]);

        $this->assertSame('cerrada', $segunda->estado);
        $this->assertEquals(150000, $this->unidad->fresh()->precio_para_margen);
    }

    /** El papeleo suele registrarse después de que el cliente se llevó el carro. */
    public function test_se_puede_registrar_la_venta_de_una_unidad_ya_entregada(): void
    {
        $this->unidad->forceFill(['estado' => EstadoUnidad::Entregada])->save();

        $venta = $this->vender(['comision_porcentaje' => 5]);

        $this->assertSame('cerrada', $venta->estado);
        $this->assertSame(EstadoUnidad::Entregada, $this->unidad->fresh()->estado);
        $this->assertEquals(2950, $venta->comision_monto);
    }

    public function test_las_ventas_de_una_empresa_no_se_ven_desde_otra(): void
    {
        $this->vender();

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Zona 11']));

        $this->assertSame(0, Venta::count());
        $this->assertSame(0, Cliente::count());
    }
}
