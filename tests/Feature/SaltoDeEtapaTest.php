<?php

namespace Tests\Feature;

use App\Actions\CambiarEstadoUnidad;
use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
use App\Models\Unidad;
use App\Models\UnidadTransicion;
use App\Support\Tenancy;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saltar etapas cuando lo pide una persona.
 *
 * Un concesionario que recién empieza a usar el sistema tiene el patio lleno
 * de carros que ya están listos, y obligarlo a registrarles diez etapas de
 * importación que ocurrieron el año pasado es pedirle que invente historia.
 *
 * Lo que no se fuerza solo es lo que dispara el programa: una venta sigue
 * exigiendo que el carro esté donde debe estar.
 */
class SaltoDeEtapaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']));
    }

    protected function unidad(EstadoUnidad $estado = EstadoUnidad::Comprada): Unidad
    {
        return Unidad::factory()->create(['estado' => $estado]);
    }

    /** La baja no es la meta del camino, es salirse de él. */
    public function test_el_recorrido_no_incluye_la_baja(): void
    {
        $recorrido = EstadoUnidad::recorrido();

        $this->assertNotContains(EstadoUnidad::Baja, $recorrido);
        $this->assertSame(EstadoUnidad::Comprada, $recorrido[0]);
        $this->assertSame(EstadoUnidad::EnCartera, end($recorrido));
        $this->assertNull(EstadoUnidad::Baja->puesto());
    }

    /**
     * Lo andado se mide por dónde está, no por dónde vino: un carro que entra
     * directo como «Lista» lleva lo mismo que uno que pasó por las diez.
     */
    public function test_lo_andado_sale_de_la_posicion(): void
    {
        $this->assertSame(0, EstadoUnidad::Comprada->porcentajeDelCamino());
        $this->assertSame(100, EstadoUnidad::EnCartera->porcentajeDelCamino());
        $this->assertGreaterThan(
            EstadoUnidad::Recibida->porcentajeDelCamino(),
            EstadoUnidad::Lista->porcentajeDelCamino(),
        );
    }

    public function test_sin_forzar_no_se_puede_saltar(): void
    {
        $this->expectException(DomainException::class);

        app(CambiarEstadoUnidad::class)->ejecutar($this->unidad(), EstadoUnidad::Entregada);
    }

    /** El carro que ya estaba en el patio cuando se instaló el sistema. */
    public function test_forzando_se_salta_el_camino_entero(): void
    {
        $unidad = $this->unidad();

        app(CambiarEstadoUnidad::class)->ejecutar($unidad, EstadoUnidad::Lista, forzado: true);

        $this->assertSame(EstadoUnidad::Lista, $unidad->fresh()->estado);
    }

    /** Y también se puede devolver hacia atrás, que antes no se podía. */
    public function test_forzando_se_puede_retroceder(): void
    {
        $unidad = $this->unidad(EstadoUnidad::Publicada);

        app(CambiarEstadoUnidad::class)->ejecutar($unidad, EstadoUnidad::EnAduana, forzado: true);

        $this->assertSame(EstadoUnidad::EnAduana, $unidad->fresh()->estado);
    }

    /** Forzar no es borrar el rastro: es no pedir permiso. */
    public function test_el_salto_queda_anotado_de_donde_venia(): void
    {
        $unidad = $this->unidad();

        app(CambiarEstadoUnidad::class)->ejecutar(
            $unidad,
            EstadoUnidad::Vendida,
            'Ya estaba vendido cuando empezamos a usar el sistema.',
            forzado: true,
        );

        $rastro = UnidadTransicion::where('unidad_id', $unidad->getKey())->latest('id')->first();

        $this->assertSame(EstadoUnidad::Comprada, $rastro->estado_anterior);
        $this->assertSame(EstadoUnidad::Vendida, $rastro->estado_nuevo);
        $this->assertStringContainsString('Ya estaba vendido', $rastro->nota);
    }

    /** Las fechas hito se sellan igual aunque se haya llegado de un salto. */
    public function test_el_salto_sella_las_fechas_hito(): void
    {
        $unidad = $this->unidad();

        app(CambiarEstadoUnidad::class)->ejecutar($unidad, EstadoUnidad::Lista, forzado: true);

        $this->assertNotNull($unidad->fresh()->fecha_lista);
    }
}
