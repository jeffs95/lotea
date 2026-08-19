<?php

namespace Tests\Feature;

use App\Actions\AnularMovimientoCaja;
use App\Actions\CobrarVentaEnCaja;
use App\Actions\CrearEmpresa;
use App\Actions\RegistrarVenta;
use App\Enums\EstadoUnidad;
use App\Filament\Resources\Ventas\Pages\ListVentas;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Role;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Support\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * El dueño tiene que ver en la lista de ventas cuánto le han pagado de cada
 * carro. Es la pregunta que hace todos los días y antes obligaba a ir a la
 * caja a sumar movimientos a mano.
 */
class CobradoEnElListadoDeVentasTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected User $usuario;

    protected Caja $caja;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);

        $this->usuario = User::factory()->create();
        $this->usuario->empresas()->attach($this->empresa);

        foreach (['Venta', 'MovimientoCaja', 'Caja', 'Cliente', 'Unidad'] as $modelo) {
            foreach (['ViewAny', 'View', 'Create', 'Update'] as $accion) {
                Permission::findOrCreate("{$accion}:{$modelo}", 'web');
            }
        }

        Tenancy::comoEmpresa($this->empresa, function () {
            Role::findByName('dueno', 'web')->syncPermissions(Permission::all());
            $this->usuario->assignRole('dueno');

            $this->caja = Caja::factory()->create(['saldo_inicial' => 0]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function vender(float $precio = 100000): Venta
    {
        return Tenancy::comoEmpresa($this->empresa, function () use ($precio) {
            $unidad = Unidad::factory()->publicada()->create([
                'estado' => EstadoUnidad::Publicada,
                'precio_lista' => $precio,
            ]);

            return app(RegistrarVenta::class)->ejecutar($unidad, [
                'cliente_id' => Cliente::factory()->create()->id,
                'estado' => 'cerrada',
                'precio_venta' => $precio,
            ]);
        });
    }

    protected function abonar(Venta $venta, float $monto): void
    {
        Tenancy::comoEmpresa($this->empresa, fn () => app(CobrarVentaEnCaja::class)
            ->ejecutar($venta, $this->caja, ['monto' => $monto, 'categoria' => 'enganche']));
    }

    /**
     * Se mira por la tabla de verdad, no por una consulta escrita aquí: lo que
     * se está probando es que el dueño vea el dato en su pantalla.
     */
    protected function tabla(): Testable
    {
        $this->actingAs($this->usuario);
        Tenancy::usar($this->empresa);
        Filament::setTenant($this->empresa);

        return Livewire::test(ListVentas::class)->assertSuccessful();
    }

    public function test_el_dueno_ve_la_columna_de_lo_cobrado(): void
    {
        $this->vender();

        $this->tabla()->assertTableColumnVisible('cobrado');
    }

    public function test_una_venta_sin_cobros_aparece_en_cero(): void
    {
        $venta = $this->vender();

        $this->tabla()->assertTableColumnStateSet('cobrado', '0', $venta);
    }

    public function test_suma_los_abonos_de_la_venta(): void
    {
        $venta = $this->vender();

        $this->abonar($venta, 30000);
        $this->abonar($venta, 15000);

        $this->tabla()->assertTableColumnStateSet('cobrado', '45000.00', $venta);
        $this->assertEquals(55000, $venta->fresh()->saldo_pendiente);
    }

    public function test_un_cobro_anulado_deja_de_contar(): void
    {
        $venta = $this->vender();
        $this->abonar($venta, 30000);

        Tenancy::comoEmpresa($this->empresa, fn () => app(AnularMovimientoCaja::class)
            ->ejecutar($venta->movimientosCaja()->first(), 'Cheque rechazado'));

        $this->tabla()->assertTableColumnStateSet('cobrado', '0', $venta);
        $this->assertEquals(100000, $venta->fresh()->saldo_pendiente);
    }

    /**
     * Guardián del withSum: si alguien lo quita del listado, el total cobrado
     * se vuelve a preguntar una vez por fila.
     *
     * Se cuenta la consulta concreta en vez de comparar dos mediciones: la
     * primera pintada calienta cachés (permisos, once()) y haría que el
     * segundo conteo saliera más bajo por razones que no tienen nada que ver
     * con el N+1.
     */
    public function test_el_listado_no_pregunta_el_cobrado_fila_por_fila(): void
    {
        foreach (range(1, 8) as $i) {
            $this->abonar($this->vender(), 1000 * $i);
        }

        $consultas = $this->consultasDelListado();

        $sumas = $this->cuantasDicen($consultas, 'sum("monto_base")');

        $this->assertLessThanOrEqual(
            1,
            $sumas,
            "Con 8 ventas el listado sumó lo cobrado {$sumas} veces: volvió el N+1.",
        );
    }

    /** Igual que el anterior, para el «¿hay caja abierta?» del botón cobrar. */
    public function test_el_listado_no_pregunta_por_las_cajas_fila_por_fila(): void
    {
        foreach (range(1, 8) as $i) {
            $this->vender();
        }

        $consultas = $this->consultasDelListado();

        $cajas = $this->cuantasDicen($consultas, 'from "cajas"');

        $this->assertLessThanOrEqual(
            1,
            $cajas,
            "Con 8 ventas el listado consultó las cajas {$cajas} veces: una por fila.",
        );
    }

    /** @return array<int, string> */
    protected function consultasDelListado(): array
    {
        $this->actingAs($this->usuario);
        Tenancy::usar($this->empresa);
        Filament::setTenant($this->empresa);

        $consultas = [];
        DB::listen(function (QueryExecuted $ejecutada) use (&$consultas) {
            $consultas[] = $ejecutada->sql;
        });

        Livewire::test(ListVentas::class)->assertSuccessful();

        return $consultas;
    }

    /** @param  array<int, string>  $consultas */
    protected function cuantasDicen(array $consultas, string $fragmento): int
    {
        return count(array_filter($consultas, fn (string $sql) => str_contains($sql, $fragmento)));
    }
}
