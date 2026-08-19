<?php

namespace Tests\Feature;

use App\Actions\AbrirOrdenTrabajo;
use App\Actions\AgregarLineaOrdenTrabajo;
use App\Actions\AnularOrdenTrabajo;
use App\Actions\CerrarOrdenTrabajo;
use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
use App\Models\CostoUnidad;
use App\Models\Empleado;
use App\Models\OrdenTrabajo;
use App\Models\Unidad;
use App\Support\Tenancy;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El taller es el costo más variable de una unidad. Si lo que se registra aquí
 * no llega bien al costeo, el margen que ve el dueño es mentira.
 */
class TallerTest extends TestCase
{
    use RefreshDatabase;

    protected Unidad $unidad;

    protected Empleado $mecanico;

    protected function setUp(): void
    {
        parent::setUp();

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']));

        $this->unidad = Unidad::factory()->create(['estado' => EstadoUnidad::Recibida]);
        $this->mecanico = Empleado::factory()->mecanico()->create(['costo_hora' => 45]);
    }

    protected function abrir(): OrdenTrabajo
    {
        return app(AbrirOrdenTrabajo::class)->ejecutar($this->unidad, ['diagnostico' => 'Golpe frontal']);
    }

    protected function agregar(OrdenTrabajo $orden, array $datos)
    {
        return app(AgregarLineaOrdenTrabajo::class)->ejecutar($orden, $datos);
    }

    public function test_abrir_una_orden_manda_la_unidad_al_taller(): void
    {
        $orden = $this->abrir();

        $this->assertSame('OT-0001', $orden->numero);
        $this->assertSame('abierta', $orden->estado);
        $this->assertSame(EstadoUnidad::EnTaller, $this->unidad->fresh()->estado);
    }

    /** Un carro que va en el barco no puede entrar al taller. */
    public function test_abrir_una_orden_no_fuerza_un_estado_imposible(): void
    {
        $enCamino = Unidad::factory()->create(['estado' => EstadoUnidad::Embarcada]);

        app(AbrirOrdenTrabajo::class)->ejecutar($enCamino);

        $this->assertSame(EstadoUnidad::Embarcada, $enCamino->fresh()->estado);
    }

    public function test_la_mano_de_obra_toma_el_costo_por_hora_del_mecanico(): void
    {
        $orden = $this->abrir();

        $linea = $this->agregar($orden, [
            'tipo' => 'mano_obra',
            'descripcion' => 'Enderezado de guardafango',
            'empleado_id' => $this->mecanico->id,
            'cantidad' => 6,
        ]);

        $this->assertEquals(45, $linea->costo_unitario);
        $this->assertEquals(270, $linea->total);
        $this->assertEquals(270, $orden->fresh()->total_mano_obra);
    }

    public function test_los_totales_se_separan_por_tipo(): void
    {
        $orden = $this->abrir();

        $this->agregar($orden, ['tipo' => 'mano_obra', 'descripcion' => 'Armado', 'empleado_id' => $this->mecanico->id, 'cantidad' => 4]);
        $this->agregar($orden, ['tipo' => 'repuesto', 'descripcion' => 'Guardafango', 'cantidad' => 1, 'costo_unitario' => 2800]);
        $this->agregar($orden, ['tipo' => 'repuesto', 'descripcion' => 'Faro derecho', 'cantidad' => 2, 'costo_unitario' => 950]);
        $this->agregar($orden, ['tipo' => 'tercero', 'descripcion' => 'Pintura completa', 'cantidad' => 1, 'costo_unitario' => 3500]);

        $orden = $orden->fresh();

        $this->assertEquals(180, $orden->total_mano_obra);
        $this->assertEquals(4700, $orden->total_repuestos);
        $this->assertEquals(3500, $orden->total_terceros);
        $this->assertEquals(8380, $orden->total);
    }

    public function test_cerrar_la_orden_le_carga_el_costo_a_la_unidad(): void
    {
        $orden = $this->abrir();

        $this->agregar($orden, ['tipo' => 'mano_obra', 'descripcion' => 'Armado', 'empleado_id' => $this->mecanico->id, 'cantidad' => 4]);
        $this->agregar($orden, ['tipo' => 'repuesto', 'descripcion' => 'Guardafango', 'cantidad' => 1, 'costo_unitario' => 2800]);
        $this->agregar($orden, ['tipo' => 'tercero', 'descripcion' => 'Pintura', 'cantidad' => 1, 'costo_unitario' => 3500]);

        $this->assertEquals(0, $this->unidad->fresh()->costo_total);

        app(CerrarOrdenTrabajo::class)->ejecutar($orden);

        $this->assertEquals(6480, $this->unidad->fresh()->costo_total);
        $this->assertSame(3, CostoUnidad::where('documento', $orden->numero)->count());
    }

    public function test_no_se_puede_cerrar_una_orden_vacia(): void
    {
        $this->expectException(DomainException::class);

        app(CerrarOrdenTrabajo::class)->ejecutar($this->abrir());
    }

    /** Cerrar dos veces duplicaría el costo del carro. */
    public function test_cerrar_dos_veces_no_duplica_el_costo(): void
    {
        $orden = $this->abrir();
        $this->agregar($orden, ['tipo' => 'repuesto', 'descripcion' => 'Batería', 'cantidad' => 1, 'costo_unitario' => 900]);

        $accion = app(CerrarOrdenTrabajo::class);
        $accion->ejecutar($orden);

        try {
            $accion->ejecutar($orden->fresh());
        } catch (DomainException) {
            // Se esperaba: ya estaba cerrada.
        }

        $this->assertEquals(900, $this->unidad->fresh()->costo_total);
        $this->assertSame(1, CostoUnidad::where('documento', $orden->numero)->count());
    }

    public function test_una_orden_cerrada_no_admite_mas_lineas(): void
    {
        $orden = $this->abrir();
        $this->agregar($orden, ['tipo' => 'repuesto', 'descripcion' => 'Batería', 'cantidad' => 1, 'costo_unitario' => 900]);
        app(CerrarOrdenTrabajo::class)->ejecutar($orden);

        $this->expectException(DomainException::class);

        $this->agregar($orden->fresh(), ['tipo' => 'repuesto', 'descripcion' => 'Otra cosa', 'cantidad' => 1, 'costo_unitario' => 100]);
    }

    public function test_anular_la_orden_le_quita_el_costo_a_la_unidad(): void
    {
        $orden = $this->abrir();
        $this->agregar($orden, ['tipo' => 'repuesto', 'descripcion' => 'Guardafango', 'cantidad' => 1, 'costo_unitario' => 2800]);
        app(CerrarOrdenTrabajo::class)->ejecutar($orden);

        $this->assertEquals(2800, $this->unidad->fresh()->costo_total);

        app(AnularOrdenTrabajo::class)->ejecutar($orden->fresh(), 'Se abrió por error');

        $this->assertEquals(0, $this->unidad->fresh()->costo_total);
        $this->assertTrue($orden->fresh()->estaAnulada());
        $this->assertNotNull(CostoUnidad::where('documento', $orden->numero)->first()->anulado_en);
    }

    public function test_no_se_puede_anular_sin_motivo(): void
    {
        $orden = $this->abrir();

        $this->expectException(DomainException::class);

        app(AnularOrdenTrabajo::class)->ejecutar($orden, '  ');
    }

    public function test_la_cantidad_tiene_que_ser_mayor_que_cero(): void
    {
        $orden = $this->abrir();

        $this->expectException(DomainException::class);

        $this->agregar($orden, ['tipo' => 'repuesto', 'descripcion' => 'Nada', 'cantidad' => 0, 'costo_unitario' => 100]);
    }

    public function test_las_ordenes_de_una_empresa_no_se_ven_desde_otra(): void
    {
        $this->abrir();

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Zona 11']));

        $this->assertSame(0, OrdenTrabajo::count());
    }
}
