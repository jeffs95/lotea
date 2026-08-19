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

class CicloDeVidaDeLaUnidadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']));
    }

    public function test_avanzar_de_estado_deja_rastro_en_el_historial(): void
    {
        $unidad = Unidad::factory()->create(['estado' => EstadoUnidad::Comprada]);

        (new CambiarEstadoUnidad)->ejecutar($unidad, EstadoUnidad::EnTitulo, 'Título solicitado a Copart');

        $this->assertSame(EstadoUnidad::EnTitulo, $unidad->fresh()->estado);

        $transicion = UnidadTransicion::where('unidad_id', $unidad->id)->first();
        $this->assertSame(EstadoUnidad::Comprada, $transicion->estado_anterior);
        $this->assertSame(EstadoUnidad::EnTitulo, $transicion->estado_nuevo);
        $this->assertSame('Título solicitado a Copart', $transicion->nota);
    }

    public function test_no_se_puede_saltar_de_comprada_a_vendida(): void
    {
        $unidad = Unidad::factory()->create(['estado' => EstadoUnidad::Comprada]);

        $this->expectException(DomainException::class);

        (new CambiarEstadoUnidad)->ejecutar($unidad, EstadoUnidad::Vendida);
    }

    public function test_una_unidad_puede_regresar_al_taller_desde_publicada(): void
    {
        $unidad = Unidad::factory()->create(['estado' => EstadoUnidad::Publicada]);

        (new CambiarEstadoUnidad)->ejecutar($unidad, EstadoUnidad::EnTaller, 'Salió con falla en el A/C');

        $this->assertSame(EstadoUnidad::EnTaller, $unidad->fresh()->estado);
    }

    public function test_las_fechas_hito_se_sellan_una_sola_vez(): void
    {
        $accion = new CambiarEstadoUnidad;
        $unidad = Unidad::factory()->create(['estado' => EstadoUnidad::Recibida]);

        $accion->ejecutar($unidad, EstadoUnidad::Lista);
        $primeraVez = $unidad->fresh()->fecha_lista;

        $accion->ejecutar($unidad->fresh(), EstadoUnidad::EnTaller);
        $accion->ejecutar($unidad->fresh(), EstadoUnidad::Lista);

        $this->assertEquals($primeraVez, $unidad->fresh()->fecha_lista);
    }

    public function test_al_venderse_deja_de_estar_publicada(): void
    {
        $unidad = Unidad::factory()->create([
            'estado' => EstadoUnidad::Publicada,
            'publicado' => true,
        ]);

        (new CambiarEstadoUnidad)->ejecutar($unidad, EstadoUnidad::Vendida);

        $this->assertFalse($unidad->fresh()->publicado);
        $this->assertNotNull($unidad->fresh()->fecha_venta);
    }

    public function test_la_preventa_se_permite_desde_que_esta_embarcada(): void
    {
        $this->assertFalse(EstadoUnidad::Comprada->admitePreventa());
        $this->assertTrue(EstadoUnidad::Embarcada->admitePreventa());
        $this->assertTrue(EstadoUnidad::EnAduana->admitePreventa());
    }

    public function test_lo_vendido_ya_no_cuenta_como_inventario(): void
    {
        Unidad::factory()->count(3)->create(['estado' => EstadoUnidad::EnTaller]);
        Unidad::factory()->count(2)->create(['estado' => EstadoUnidad::Entregada]);

        $this->assertSame(3, Unidad::enInventario()->count());
        $this->assertSame(5, Unidad::count());
    }

    public function test_el_historial_de_una_empresa_no_se_ve_desde_otra(): void
    {
        $unidad = Unidad::factory()->create();
        (new CambiarEstadoUnidad)->ejecutar($unidad, EstadoUnidad::EnTitulo);

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Zona 11']));

        $this->assertSame(0, Unidad::count());
        $this->assertSame(0, UnidadTransicion::count());
    }
}
