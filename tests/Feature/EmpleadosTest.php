<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\Empleado;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpleadosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']));
    }

    public function test_el_ingreso_mensual_suma_el_salario_y_la_bonificacion(): void
    {
        $empleado = Empleado::factory()->create([
            'salario_base' => 3500,
            'bonificacion_incentivo' => 250,
        ]);

        $this->assertEquals(3750, $empleado->ingreso_mensual);
    }

    public function test_la_antiguedad_se_cuenta_desde_el_ingreso(): void
    {
        $empleado = Empleado::factory()->create(['fecha_ingreso' => now()->subYears(3)]);

        $this->assertEqualsWithDelta(3.0, $empleado->antiguedad, 0.1);
    }

    /** Un empleado de baja deja de contar aunque su ficha siga ahí. */
    public function test_dar_de_baja_lo_saca_de_los_activos(): void
    {
        $empleado = Empleado::factory()->create();

        $this->assertSame(1, Empleado::activos()->count());

        $empleado->update(['fecha_baja' => now(), 'motivo_baja' => 'Renuncia']);

        $this->assertTrue($empleado->fresh()->estaDeBaja());
        $this->assertSame(0, Empleado::activos()->count());
        $this->assertSame(1, Empleado::count());
    }

    public function test_solo_los_mecanicos_activos_aparecen_para_el_taller(): void
    {
        Empleado::factory()->mecanico()->count(2)->create();
        Empleado::factory()->create();                       // administrativo
        Empleado::factory()->mecanico()->create(['fecha_baja' => now()]);

        $this->assertSame(2, Empleado::mecanicos()->count());
    }

    public function test_la_antiguedad_se_congela_al_dar_de_baja(): void
    {
        $empleado = Empleado::factory()->create([
            'fecha_ingreso' => now()->subYears(5),
            'fecha_baja' => now()->subYears(2),
        ]);

        $this->assertEqualsWithDelta(3.0, $empleado->antiguedad, 0.1);
    }

    public function test_los_empleados_de_una_empresa_no_se_ven_desde_otra(): void
    {
        Empleado::factory()->count(3)->create();

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Zona 11']));

        $this->assertSame(0, Empleado::count());
    }

    public function test_el_codigo_no_se_repite_dentro_de_la_misma_empresa(): void
    {
        Empleado::factory()->create(['codigo' => 'EMP-001']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Empleado::factory()->create(['codigo' => 'EMP-001']);
    }
}
