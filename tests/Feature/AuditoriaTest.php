<?php

namespace Tests\Feature;

use App\Actions\AnularCosto;
use App\Actions\CrearEmpresa;
use App\Actions\RegistrarCosto;
use App\Actions\RegistrarMovimientoCaja;
use App\Filament\Pages\Auditoria;
use App\Models\Caja;
use App\Models\CategoriaCosto;
use App\Models\Empresa;
use App\Models\Rastro;
use App\Models\Unidad;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El rastro de lo que pasa con el dinero.
 *
 * Se prometió desde el primer día que nada de dinero se borra y que todo queda
 * auditado. La anulación estaba; esto es la otra mitad. El día que un cliente
 * diga que le desapareció un gasto de Q9,000, esto es lo que responde.
 */
class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected Unidad $unidad;

    protected User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        Tenancy::usar($this->empresa);

        $this->usuario = User::factory()->create(['name' => 'Andrea Godínez']);
        $this->usuario->empresas()->attach($this->empresa);
        $this->actingAs($this->usuario);

        $this->unidad = Unidad::factory()->create();
    }

    protected function categoria(string $codigo): CategoriaCosto
    {
        return CategoriaCosto::where('codigo', $codigo)->firstOrFail();
    }

    public function test_registrar_un_gasto_queda_en_el_rastro_con_su_autor(): void
    {
        app(RegistrarCosto::class)->ejecutar($this->unidad, [
            'categoria_costo_id' => $this->categoria('repuestos')->id,
            'monto' => 9450,
        ]);

        $rastro = Rastro::where('log_name', 'gasto de unidad')->latest('id')->first();

        $this->assertNotNull($rastro);
        $this->assertSame('creó gasto de unidad '.$this->unidad->costos()->first()->id, $rastro->description);
        $this->assertSame('Andrea Godínez', $rastro->quien);
        $this->assertSame($this->empresa->id, $rastro->empresa_id);
    }

    /** El caso que da sentido a todo esto. */
    public function test_anular_un_gasto_deja_constancia_de_quien_y_por_que(): void
    {
        $costo = app(RegistrarCosto::class)->ejecutar($this->unidad, [
            'categoria_costo_id' => $this->categoria('repuestos')->id,
            'monto' => 9000,
        ]);

        app(AnularCosto::class)->ejecutar($costo, 'Factura duplicada');

        $rastro = Rastro::where('log_name', 'gasto de unidad')
            ->where('properties', 'like', '%motivo_anulacion%')
            ->latest('id')
            ->first();

        $this->assertNotNull($rastro);
        $this->assertSame('Andrea Godínez', $rastro->quien);

        $campos = collect($rastro->cambios())->pluck('campo');
        $this->assertTrue($campos->contains('anulado_en'));
        $this->assertTrue($campos->contains('motivo_anulacion'));
    }

    public function test_el_rastro_guarda_el_valor_viejo_y_el_nuevo(): void
    {
        $costo = app(RegistrarCosto::class)->ejecutar($this->unidad, [
            'categoria_costo_id' => $this->categoria('repuestos')->id,
            'monto' => 1000,
        ]);

        $costo->update(['monto' => 2500]);

        $cambio = collect(Rastro::latest('id')->first()->cambios())->firstWhere('campo', 'monto');

        $this->assertSame('1000.00', (string) $cambio['antes']);
        $this->assertEquals(2500, $cambio['despues']);
    }

    public function test_los_movimientos_de_caja_tambien_se_auditan(): void
    {
        $caja = Caja::factory()->create();

        app(RegistrarMovimientoCaja::class)->ejecutar($caja, [
            'tipo' => 'ingreso',
            'monto' => 5000,
            'descripcion' => 'Cobro de venta',
        ]);

        $this->assertSame(1, Rastro::where('log_name', 'movimiento de caja')->count());
    }

    public function test_el_cambio_de_precio_de_una_unidad_queda_registrado(): void
    {
        $this->unidad->update(['precio_lista' => 148000]);

        $cambio = collect(Rastro::where('log_name', 'unidad')->latest('id')->first()->cambios())
            ->firstWhere('campo', 'precio_lista');

        $this->assertNotNull($cambio);
        $this->assertEquals(148000, $cambio['despues']);
    }

    /** Cambiar algo que no es dinero ni estado no debe llenar el rastro. */
    public function test_no_se_registra_lo_que_no_importa(): void
    {
        $antes = Rastro::count();

        $this->unidad->update([
            'notas' => 'Le falta el tapón de la gasolina',
            'color' => 'Verde',
            'ubicacion' => 'Fila 3',
        ]);

        $this->assertSame($antes, Rastro::count());
    }

    public function test_el_rastro_de_una_empresa_no_se_ve_desde_otra(): void
    {
        app(RegistrarCosto::class)->ejecutar($this->unidad, [
            'categoria_costo_id' => $this->categoria('repuestos')->id,
            'monto' => 500,
        ]);

        $this->assertGreaterThan(0, Rastro::count());

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Zona 11']));

        $this->assertSame(0, Rastro::count());
    }

    /** El rastro se lee, no se descifra. */
    public function test_los_cambios_se_muestran_en_lenguaje_normal(): void
    {
        $this->unidad->update(['precio_lista' => 148000]);
        $this->unidad->update(['publicado' => false, 'precio_minimo' => 140000]);

        $legible = Rastro::where('log_name', 'unidad')->latest('id')->first()->cambiosLegibles();

        // Nombres de campo en español y no columnas crudas.
        $this->assertStringContainsString('precio mínimo', $legible);
        $this->assertStringNotContainsString('precio_minimo', $legible);
    }

    public function test_los_booleanos_y_los_vacios_se_leen_como_palabras(): void
    {
        $costo = app(RegistrarCosto::class)->ejecutar($this->unidad, [
            'categoria_costo_id' => $this->categoria('repuestos')->id,
            'monto' => 100,
        ]);

        $costo->update(['es_presupuesto' => true]);

        $legible = Rastro::latest('id')->first()->cambiosLegibles();

        $this->assertStringContainsString('es presupuesto: no → sí', $legible);
    }

    /** La pantalla muestra montos: no es para cualquiera. */
    public function test_solo_quien_ve_costos_entra_a_la_auditoria(): void
    {
        $this->assertFalse(Auditoria::canAccess());
    }
}
