<?php

namespace Tests\Feature;

use App\Actions\AnularCosto;
use App\Actions\CrearEmpresa;
use App\Actions\RegistrarCosto;
use App\Actions\RegistrarGastoCompartido;
use App\Models\CategoriaCosto;
use App\Models\CostoUnidad;
use App\Models\TipoCambio;
use App\Models\Unidad;
use App\Support\Tenancy;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El costeo es el producto. Si estas cuentas están mal, el dueño toma
 * decisiones de compra con números falsos, que es peor que no tener sistema.
 */
class CosteoDeUnidadTest extends TestCase
{
    use RefreshDatabase;

    protected Unidad $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']));

        $this->unidad = Unidad::factory()->create();
    }

    protected function categoria(string $codigo): CategoriaCosto
    {
        return CategoriaCosto::where('codigo', $codigo)->firstOrFail();
    }

    protected function registrar(array $datos): CostoUnidad
    {
        return app(RegistrarCosto::class)->ejecutar($this->unidad, $datos);
    }

    public function test_los_gastos_se_suman_al_costo_de_la_unidad(): void
    {
        $this->registrar([
            'categoria_costo_id' => $this->categoria('iprima')->id,
            'monto' => 18900,
        ]);

        $this->registrar([
            'categoria_costo_id' => $this->categoria('repuestos')->id,
            'monto' => 9450,
        ]);

        $this->assertEquals(28350, $this->unidad->fresh()->costo_total);
    }

    public function test_un_gasto_en_dolares_se_convierte_con_su_tipo_de_cambio(): void
    {
        $costo = $this->registrar([
            'categoria_costo_id' => $this->categoria('precio_compra')->id,
            'monto' => 4200,
            'moneda' => 'USD',
            'tipo_cambio' => 7.70,
        ]);

        $this->assertEquals(4200, $costo->monto);
        $this->assertEquals('USD', $costo->moneda);
        $this->assertEquals(32340, $costo->monto_base);
        $this->assertEquals(32340, $this->unidad->fresh()->costo_total);
    }

    public function test_si_no_se_indica_el_tipo_de_cambio_se_usa_el_de_referencia(): void
    {
        TipoCambio::create(['fecha' => now()->subDay(), 'moneda' => 'USD', 'tasa' => 7.65]);

        $costo = $this->registrar([
            'categoria_costo_id' => $this->categoria('flete_maritimo')->id,
            'monto' => 1000,
            'moneda' => 'USD',
        ]);

        $this->assertEquals(7.65, $costo->tipo_cambio);
        $this->assertEquals(7650, $costo->monto_base);
    }

    /** La comisión del vendedor sale de la utilidad, no encarece el carro. */
    public function test_las_categorias_que_no_afectan_costo_no_suman(): void
    {
        $this->registrar([
            'categoria_costo_id' => $this->categoria('repuestos')->id,
            'monto' => 5000,
        ]);

        $this->registrar([
            'categoria_costo_id' => $this->categoria('comision_vendedor')->id,
            'monto' => 2360,
        ]);

        $this->assertEquals(5000, $this->unidad->fresh()->costo_total);
    }

    public function test_lo_presupuestado_se_lleva_aparte_de_lo_real(): void
    {
        $this->registrar([
            'categoria_costo_id' => $this->categoria('flete_maritimo')->id,
            'monto' => 8000,
            'es_presupuesto' => true,
        ]);

        $this->registrar([
            'categoria_costo_id' => $this->categoria('flete_maritimo')->id,
            'monto' => 8470,
        ]);

        $unidad = $this->unidad->fresh();

        $this->assertEquals(8470, $unidad->costo_total);
        $this->assertEquals(8000, $unidad->costo_presupuestado);
    }

    public function test_anular_un_gasto_lo_descuenta_pero_lo_deja_registrado(): void
    {
        $costo = $this->registrar([
            'categoria_costo_id' => $this->categoria('repuestos')->id,
            'monto' => 9450,
        ]);

        $this->assertEquals(9450, $this->unidad->fresh()->costo_total);

        app(AnularCosto::class)->ejecutar($costo, 'Factura duplicada');

        $this->assertEquals(0, $this->unidad->fresh()->costo_total);
        $this->assertDatabaseHas('costos_unidad', [
            'id' => $costo->id,
            'motivo_anulacion' => 'Factura duplicada',
        ]);
    }

    public function test_no_se_puede_anular_sin_decir_por_que(): void
    {
        $costo = $this->registrar([
            'categoria_costo_id' => $this->categoria('repuestos')->id,
            'monto' => 100,
        ]);

        $this->expectException(DomainException::class);

        app(AnularCosto::class)->ejecutar($costo, '   ');
    }

    public function test_el_flete_de_un_contenedor_se_reparte_entre_sus_unidades(): void
    {
        $otras = Unidad::factory()->count(3)->create();
        $todas = collect([$this->unidad])->concat($otras);

        app(RegistrarGastoCompartido::class)->ejecutar([
            'categoria_costo_id' => $this->categoria('flete_maritimo')->id,
            'descripcion' => 'Flete contenedor 40 pies MSCU1234567',
            'monto' => 4400,
        ], $todas);

        foreach ($todas as $unidad) {
            $this->assertEquals(1100, $unidad->fresh()->costo_total);
        }
    }

    /** El centavo que sobra tiene que caer en algún lado, no evaporarse. */
    public function test_el_reparto_cuadra_exacto_aunque_no_sea_divisible(): void
    {
        $unidades = collect([$this->unidad])->concat(Unidad::factory()->count(2)->create());

        $gasto = app(RegistrarGastoCompartido::class)->ejecutar([
            'categoria_costo_id' => $this->categoria('honorarios_agente')->id,
            'descripcion' => 'Honorarios póliza 3 unidades',
            'monto' => 1000,
        ], $unidades);

        $repartido = $gasto->porciones()->sum('monto_base');

        $this->assertEquals(1000, $repartido);
        $this->assertEquals(333.34, $unidades->first()->fresh()->costo_total);
        $this->assertEquals(333.33, $unidades->last()->fresh()->costo_total);
    }

    public function test_el_reparto_por_valor_carga_mas_al_carro_mas_caro(): void
    {
        $barata = Unidad::factory()->create();

        $this->registrar([
            'categoria_costo_id' => $this->categoria('precio_compra')->id,
            'monto' => 75000,
        ]);

        app(RegistrarCosto::class)->ejecutar($barata, [
            'categoria_costo_id' => $this->categoria('precio_compra')->id,
            'monto' => 25000,
        ]);

        app(RegistrarGastoCompartido::class)->ejecutar([
            'categoria_costo_id' => $this->categoria('flete_maritimo')->id,
            'descripcion' => 'Flete contenedor compartido',
            'monto' => 8000,
            'criterio' => 'por_valor',
        ], [$this->unidad, $barata]);

        // 75% y 25% del flete, sobre 75,000 y 25,000 de costo previo.
        $this->assertEquals(81000, $this->unidad->fresh()->costo_total);
        $this->assertEquals(27000, $barata->fresh()->costo_total);
    }

    public function test_los_costos_de_una_empresa_no_se_ven_desde_otra(): void
    {
        $this->registrar([
            'categoria_costo_id' => $this->categoria('repuestos')->id,
            'monto' => 5000,
        ]);

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Zona 11']));

        $this->assertSame(0, CostoUnidad::count());
    }
}
