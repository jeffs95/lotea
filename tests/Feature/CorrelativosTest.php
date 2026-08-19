<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Actions\GenerarNumeroVenta;
use App\Actions\GenerarStockNo;
use App\Actions\RegistrarVenta;
use App\Enums\EstadoUnidad;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Unidad;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El stock_no y el número de venta son los nombres con los que el dueño y los
 * vendedores hablan del carro en WhatsApp. Tienen que ser cortos, correlativos
 * y propios de cada concesionario: que el primer carro de un cliente nuevo se
 * llame 0087 porque otro cliente ya vendió 86 no tiene ninguna explicación
 * posible frente a él.
 */
class CorrelativosTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $unaEmpresa;

    protected Empresa $otraEmpresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unaEmpresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        $this->otraEmpresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Zona 11']);
    }

    protected function stockDe(Empresa $empresa, ?string $prefijo = null): string
    {
        return Tenancy::comoEmpresa($empresa, fn () => app(GenerarStockNo::class)->ejecutar($prefijo));
    }

    public function test_el_primer_carro_de_una_empresa_es_el_0001(): void
    {
        $this->assertSame('0001', $this->stockDe($this->unaEmpresa));
    }

    public function test_sigue_el_correlativo_de_la_propia_empresa(): void
    {
        Tenancy::comoEmpresa($this->unaEmpresa, function () {
            Unidad::factory()->create(['stock_no' => '0001']);
            Unidad::factory()->create(['stock_no' => '0002']);
        });

        $this->assertSame('0003', $this->stockDe($this->unaEmpresa));
    }

    /**
     * Este es el caso que importa: lo que haga un concesionario no puede
     * mover la numeración de otro.
     */
    public function test_las_unidades_de_otra_empresa_no_corren_el_correlativo(): void
    {
        Tenancy::comoEmpresa($this->otraEmpresa, function () {
            Unidad::factory()->count(5)->create();
        });

        $this->assertSame('0001', $this->stockDe($this->unaEmpresa));
    }

    public function test_el_prefijo_no_se_cuenta_como_parte_del_numero(): void
    {
        Tenancy::comoEmpresa($this->unaEmpresa, fn () => Unidad::factory()->create(['stock_no' => 'AV-0007']));

        $this->assertSame('AV-0008', $this->stockDe($this->unaEmpresa, 'AV'));
        $this->assertSame('0008', $this->stockDe($this->unaEmpresa));
    }

    /**
     * Hay un unique(empresa_id, stock_no) en la base: si el correlativo
     * reciclara el número de una unidad borrada, el alta reventaría.
     */
    public function test_cuenta_tambien_las_unidades_borradas(): void
    {
        Tenancy::comoEmpresa($this->unaEmpresa, function () {
            Unidad::factory()->create(['stock_no' => '0001']);
            Unidad::factory()->create(['stock_no' => '0002'])->delete();
        });

        $this->assertSame('0003', $this->stockDe($this->unaEmpresa));
    }

    public function test_un_stock_no_sin_digitos_no_rompe_el_correlativo(): void
    {
        Tenancy::comoEmpresa($this->unaEmpresa, fn () => Unidad::factory()->create(['stock_no' => 'DEMO']));

        $this->assertSame('0001', $this->stockDe($this->unaEmpresa));
    }

    public function test_el_numero_de_venta_arranca_en_v0001_en_cada_empresa(): void
    {
        Tenancy::comoEmpresa($this->otraEmpresa, function () {
            $unidad = Unidad::factory()->publicada()->create(['precio_lista' => 90000]);

            app(RegistrarVenta::class)->ejecutar($unidad, [
                'cliente_id' => Cliente::factory()->create()->id,
                'estado' => 'cerrada',
                'precio_venta' => 88000,
            ]);
        });

        $numero = Tenancy::comoEmpresa($this->unaEmpresa, fn () => app(GenerarNumeroVenta::class)->ejecutar());

        $this->assertSame('V-0001', $numero);
    }

    public function test_la_segunda_venta_de_una_empresa_es_la_v0002(): void
    {
        Tenancy::comoEmpresa($this->unaEmpresa, function () {
            $unidad = Unidad::factory()->publicada()->create([
                'estado' => EstadoUnidad::Publicada,
                'precio_lista' => 90000,
            ]);

            $venta = app(RegistrarVenta::class)->ejecutar($unidad, [
                'cliente_id' => Cliente::factory()->create()->id,
                'estado' => 'cerrada',
                'precio_venta' => 88000,
            ]);

            $this->assertSame('V-0001', $venta->numero);
            $this->assertSame('V-0002', app(GenerarNumeroVenta::class)->ejecutar());
        });
    }
}
