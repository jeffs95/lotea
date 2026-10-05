<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Support\RankingDeVendedores;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El cuadro que contesta a quién le rinde seguir trabajando con este patio y a
 * quién ya no. No es el listado de ventas ordenado de otra forma: la mitad de
 * la respuesta está en los que vendieron cero.
 */
class RankingDeVendedoresTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        Tenancy::usar($this->empresa);
    }

    protected function vendedor(string $nombre, ?Empresa $empresa = null): User
    {
        $usuario = User::factory()->create(['name' => $nombre]);
        $usuario->empresas()->attach(($empresa ?? $this->empresa)->getKey());

        Tenancy::comoEmpresa($empresa ?? $this->empresa, fn () => $usuario->assignRole('vendedor'));

        return $usuario->fresh();
    }

    protected function venta(User $vendedor, array $datos = []): Venta
    {
        return Venta::create([
            'unidad_id' => Unidad::factory()->create(['estado' => EstadoUnidad::Publicada])->getKey(),
            'cliente_id' => Cliente::factory()->create()->getKey(),
            'vendedor_id' => $vendedor->getKey(),
            'numero' => 'V-'.fake()->unique()->numberBetween(1, 99999),
            'estado' => 'cerrada',
            'fecha' => today(),
            'precio_venta' => '100000.00',
            'precio_final' => '100000.00',
            'comision_monto' => '1500.00',
            ...$datos,
        ]);
    }

    /** @return array<string, array> */
    protected function porNombre(string $periodo = 'mes'): array
    {
        return RankingDeVendedores::para($periodo)
            ->keyBy(fn (array $fila) => $fila['vendedor']->name)
            ->all();
    }

    public function test_quien_mas_vende_va_primero(): void
    {
        $ana = $this->vendedor('Ana');
        $bruno = $this->vendedor('Bruno');

        $this->venta($bruno);
        $this->venta($ana);
        $this->venta($ana);

        $orden = RankingDeVendedores::para()->map(fn (array $f) => $f['vendedor']->name)->all();

        $this->assertSame(['Ana', 'Bruno'], $orden);
    }

    /** El que no vendió nada es justamente al que hay que mirar. */
    public function test_el_que_no_vendio_tambien_aparece(): void
    {
        $this->vendedor('Ana');
        $this->venta($this->vendedor('Bruno'));

        $filas = $this->porNombre();

        $this->assertArrayHasKey('Ana', $filas);
        $this->assertSame(0, $filas['Ana']['carros']);
        $this->assertSame('0.00', $filas['Ana']['vendido']);
        $this->assertNull($filas['Ana']['ultima']);
    }

    /** Y de quien vendió antes, desde cuándo no vende. */
    public function test_dice_cuanto_hace_que_no_vende(): void
    {
        $ana = $this->vendedor('Ana');
        $this->venta($ana, ['fecha' => today()->subDays(90)]);

        $fila = $this->porNombre()['Ana'];

        $this->assertSame(0, $fila['carros'], 'La venta vieja no cuenta en este mes.');
        $this->assertSame(90, $fila['dias_sin_vender']);
    }

    public function test_suma_lo_vendido_y_lo_que_hay_que_pagarle(): void
    {
        $ana = $this->vendedor('Ana');
        $this->venta($ana, ['precio_final' => '120000.00', 'comision_monto' => '2000.00']);
        $this->venta($ana, ['precio_final' => '80000.50', 'comision_monto' => '1500.00']);

        $fila = $this->porNombre()['Ana'];

        $this->assertSame(2, $fila['carros']);
        $this->assertSame('200000.50', $fila['vendido']);
        $this->assertSame('3500.00', $fila['comisiones']);
    }

    /** Una venta anulada no se le cuenta a nadie: no hubo carro ni comisión. */
    public function test_las_anuladas_no_cuentan(): void
    {
        $ana = $this->vendedor('Ana');
        $this->venta($ana);
        $this->venta($ana, ['anulada_en' => now(), 'estado' => 'anulada']);

        $this->assertSame(1, $this->porNombre()['Ana']['carros']);
    }

    /** Una cotización tampoco: todavía no vendió nada. */
    public function test_lo_que_no_esta_cerrado_no_cuenta(): void
    {
        $ana = $this->vendedor('Ana');
        $this->venta($ana, ['estado' => 'cotizacion']);

        $this->assertSame(0, $this->porNombre()['Ana']['carros']);
    }

    public function test_el_mes_pasado_se_mira_aparte(): void
    {
        $ana = $this->vendedor('Ana');
        $this->venta($ana, ['fecha' => today()->subMonthNoOverflow()->startOfMonth()->addDay()]);

        $this->assertSame(0, $this->porNombre('mes')['Ana']['carros']);
        $this->assertSame(1, $this->porNombre('mes_pasado')['Ana']['carros']);
        $this->assertSame(1, $this->porNombre('todo')['Ana']['carros']);
    }

    /** El vendedor de otro concesionario no sale en este cuadro. */
    public function test_no_se_mezclan_los_concesionarios(): void
    {
        $otra = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Norte']);
        $ajeno = $this->vendedor('Ajeno', $otra);

        Tenancy::comoEmpresa($otra, fn () => $this->venta($ajeno));

        $this->vendedor('Ana');

        $this->assertArrayNotHasKey('Ajeno', $this->porNombre('todo'));
    }
}
