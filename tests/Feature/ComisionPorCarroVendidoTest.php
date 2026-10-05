<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Actions\RegistrarCosto;
use App\Actions\RegistrarVenta;
use App\Enums\EstadoUnidad;
use App\Models\CategoriaCosto;
use App\Models\Cliente;
use App\Models\CostoUnidad;
use App\Models\Empresa;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La comisión de monto fijo: lo acordado por carro vendido.
 *
 * El sistema solo sabía calcular porcentajes, del margen o del precio. Eso
 * sirve para un vendedor de planta, pero no para como se trabaja aquí: hay
 * gente de fuera del negocio que publica los carros en sus redes y cobra un
 * monto pactado por cada uno que sale, sin importar en cuánto se vendió.
 *
 * Atarle el pago al margen a alguien que no participa del precio no tendría
 * sentido ni para él ni para el dueño.
 */
class ComisionPorCarroVendidoTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected Unidad $unidad;

    protected Cliente $cliente;

    protected User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        Tenancy::usar($this->empresa);

        $this->unidad = Unidad::factory()->publicada()->create([
            'estado' => EstadoUnidad::Publicada,
            'precio_lista' => 148000,
        ]);

        // El costo real entra por donde entra siempre, para que el margen que
        // se calcula aquí sea el mismo que calcularía el sistema de verdad.
        app(RegistrarCosto::class)->ejecutar($this->unidad, [
            'categoria_costo_id' => CategoriaCosto::where('codigo', 'precio_compra')->value('id'),
            'monto' => 86000,
        ]);

        $this->cliente = Cliente::factory()->create();

        $this->vendedor = User::factory()->create();
        $this->vendedor->empresas()->attach($this->empresa);
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

    // ── El monto fijo ───────────────────────────────────────────────────────

    public function test_se_paga_lo_acordado_sin_importar_el_precio(): void
    {
        $venta = $this->vender([
            'comision_base' => RegistrarVenta::COMISION_FIJA,
            'comision_acordada' => 1500,
        ]);

        $this->assertEquals(1500, $venta->comision_monto);
    }

    /** Dos carros a precios muy distintos pagan lo mismo: eso es lo pactado. */
    public function test_el_precio_del_carro_no_cambia_lo_que_cobra(): void
    {
        $barato = $this->vender([
            'precio_venta' => 90000,
            'comision_base' => RegistrarVenta::COMISION_FIJA,
            'comision_acordada' => 1500,
        ]);

        $this->assertEquals(1500, $barato->comision_monto);
    }

    /** Ni el porcentaje que haya quedado escrito de antes. */
    public function test_el_porcentaje_se_ignora_cuando_el_monto_es_fijo(): void
    {
        $venta = $this->vender([
            'comision_base' => RegistrarVenta::COMISION_FIJA,
            'comision_acordada' => 1500,
            'comision_porcentaje' => 5,
        ]);

        $this->assertEquals(1500, $venta->comision_monto);
    }

    public function test_sin_monto_acordado_no_hay_comision(): void
    {
        $venta = $this->vender(['comision_base' => RegistrarVenta::COMISION_FIJA]);

        $this->assertEquals(0, $venta->comision_monto);
    }

    /**
     * Lo acordado se guarda aparte de lo pagado.
     *
     * Con una sola columna no se podría saber después si un monto raro fue un
     * acuerdo especial o una cuenta mal hecha.
     */
    public function test_queda_registrado_lo_que_se_acordo_y_lo_que_se_pago(): void
    {
        $venta = $this->vender([
            'comision_base' => RegistrarVenta::COMISION_FIJA,
            'comision_acordada' => 1500,
        ]);

        $this->assertEquals(1500, $venta->comision_acordada);
        $this->assertEquals(1500, $venta->comision_monto);
    }

    /** Y entra como gasto igual que la de porcentaje. */
    public function test_la_comision_fija_tambien_entra_como_gasto(): void
    {
        $this->vender([
            'comision_base' => RegistrarVenta::COMISION_FIJA,
            'comision_acordada' => 1500,
        ]);

        $gasto = CostoUnidad::whereHas('categoria', fn ($q) => $q->where('codigo', 'comision_vendedor'))->first();

        $this->assertNotNull($gasto, 'La comisión fija no quedó registrada como gasto.');
        $this->assertEquals(1500, $gasto->monto);
    }

    // ── Lo que ya funcionaba sigue igual ────────────────────────────────────

    public function test_el_porcentaje_sobre_el_margen_no_cambio(): void
    {
        $venta = $this->vender(['comision_porcentaje' => 5]);

        // (145000 − 86000) × 5%
        $this->assertEquals(2950, $venta->comision_monto);
    }

    // ── Lo acordado con cada vendedor ───────────────────────────────────────

    /**
     * El monto vive en la relación del vendedor con el concesionario: la misma
     * persona podría vender para dos y haber pactado distinto con cada uno.
     */
    public function test_cada_concesionario_acuerda_lo_suyo_con_el_mismo_vendedor(): void
    {
        $otra = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Norte']);
        $this->vendedor->empresas()->attach($otra);

        $this->empresa->usuarios()->updateExistingPivot($this->vendedor->id, ['comision_por_venta' => 1500]);
        $otra->usuarios()->updateExistingPivot($this->vendedor->id, ['comision_por_venta' => 900]);

        $vendedor = $this->vendedor->fresh();

        $this->assertSame('1500.00', $vendedor->comisionAcordadaCon($this->empresa));
        $this->assertSame('900.00', $vendedor->comisionAcordadaCon($otra));
    }

    public function test_sin_nada_acordado_no_se_inventa_un_monto(): void
    {
        $this->assertNull($this->vendedor->fresh()->comisionAcordadaCon($this->empresa));
    }
}
