<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
use App\Models\Cliente;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quién cargó cada venta y cuánto después de hacerse.
 *
 * Los vendedores externos cargan lo suyo cuando pueden, y más de uno lo hace
 * el viernes de corrido. Se decidió que la venta quede firme al cargarla, sin
 * que nadie la apruebe, para no trabar a quien está en la calle. Esto es lo
 * que queda entonces para poder revisar después: el rastro de quién la
 * escribió y de cuándo.
 */
class TrazabilidadDeLaCargaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']));
    }

    protected function venta(array $datos = []): Venta
    {
        return Venta::create([
            'unidad_id' => Unidad::factory()->create(['estado' => EstadoUnidad::Publicada])->getKey(),
            'cliente_id' => Cliente::factory()->create()->getKey(),
            'numero' => 'V-'.fake()->unique()->numberBetween(1, 9999),
            'estado' => 'cerrada',
            'fecha' => today(),
            'precio_venta' => '100000.00',
            'precio_final' => '100000.00',
            ...$datos,
        ]);
    }

    public function test_queda_anotado_quien_la_cargo(): void
    {
        $vendedor = User::factory()->create(['name' => 'Ana']);

        $venta = $this->actingAs($vendedor)->app->call(
            fn () => $this->venta(['user_id' => auth()->id()])
        );

        $this->assertTrue($venta->registradaPor->is($vendedor));
    }

    public function test_cargada_el_mismo_dia_no_dice_nada_raro(): void
    {
        $venta = $this->venta();

        $this->assertSame(0, $venta->diasEntreVentaYCarga());
        $this->assertSame(now()->format('d/m/Y'), $venta->resumen_de_carga);
    }

    /** El caso del viernes: se vendió el lunes y se escribió al cierre. */
    public function test_la_carga_en_diferido_se_nota(): void
    {
        $venta = $this->venta(['fecha' => today()->subDays(4)]);

        $this->assertSame(4, $venta->diasEntreVentaYCarga());
        $this->assertStringContainsString('4 días después', $venta->resumen_de_carga);
    }

    public function test_un_solo_dia_se_dice_en_singular(): void
    {
        $this->assertStringContainsString(
            '1 día después',
            $this->venta(['fecha' => today()->subDay()])->resumen_de_carga,
        );
    }

    /**
     * Una venta con fecha adelantada —una reserva que se firma para el lunes—
     * no es una carga tardía, y no tiene por qué salir en rojo ni en negativo.
     */
    public function test_una_fecha_futura_no_cuenta_como_atraso(): void
    {
        $venta = $this->venta(['fecha' => today()->addDays(3)]);

        $this->assertSame(0, $venta->diasEntreVentaYCarga());
        $this->assertStringNotContainsString('después', $venta->resumen_de_carga);
    }
}
