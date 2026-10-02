<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
use App\Filament\Resources\Unidades\Actions\CambiarEstadoAction;
use App\Models\Empresa;
use App\Models\Unidad;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El modal de cambiar estado.
 *
 * Mostraba solo el selector vacío, así que quien lo abría no sabía en qué
 * etapa estaba el carro —parecía que no tuviera ninguna— y tenía que cerrarlo
 * para ir a mirarlo.
 */
class CambiarEstadoDesdeElPanelTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        Tenancy::usar($this->empresa);
    }

    /** El texto del recuadro «Hoy está en», sin etiquetas. */
    protected function loQueDice(Unidad $unidad): string
    {
        return strip_tags(CambiarEstadoAction::dondeEsta($unidad));
    }

    public function test_el_modal_dice_en_que_etapa_esta(): void
    {
        $unidad = Unidad::factory()->create([
            'estado' => EstadoUnidad::EnAduana,
            'estado_desde' => now()->subDays(23),
        ]);

        $this->assertStringContainsString(EstadoUnidad::EnAduana->getLabel(), $this->loQueDice($unidad));
    }

    /**
     * Y cuánto lleva ahí, que importa tanto como la etapa: «En aduana» no dice
     * nada, «En aduana desde hace 23 días» dice que hay que llamar al agente.
     */
    public function test_tambien_dice_cuanto_lleva(): void
    {
        $unidad = Unidad::factory()->create([
            'estado' => EstadoUnidad::EnAduana,
            'estado_desde' => now()->subDays(23),
        ]);

        $this->assertStringContainsString('23 días', $this->loQueDice($unidad));
    }

    public function test_un_dia_se_dice_en_singular(): void
    {
        $unidad = Unidad::factory()->create([
            'estado' => EstadoUnidad::EnAduana,
            'estado_desde' => now()->subDay(),
        ]);

        $texto = $this->loQueDice($unidad);

        $this->assertStringContainsString('1 día', $texto);
        $this->assertStringNotContainsString('1 días', $texto);
    }

    public function test_el_mismo_dia_no_dice_cero_dias(): void
    {
        $unidad = Unidad::factory()->create([
            'estado' => EstadoUnidad::EnAduana,
            'estado_desde' => now(),
        ]);

        $texto = $this->loQueDice($unidad);

        $this->assertStringContainsString('desde hoy', $texto);
        $this->assertStringNotContainsString('0 día', $texto);
    }

    /** Sin fecha de cambio, se dice la etapa y ya: no se inventa una antigüedad. */
    public function test_sin_fecha_solo_dice_la_etapa(): void
    {
        $unidad = Unidad::factory()->create([
            'estado' => EstadoUnidad::EnAduana,
            'estado_desde' => null,
        ]);

        $texto = $this->loQueDice($unidad);

        $this->assertStringContainsString(EstadoUnidad::EnAduana->getLabel(), $texto);
        $this->assertStringNotContainsString('desde', $texto);
    }

    /** Y lo que ya funcionaba: nunca se ofrece la etapa en la que ya está. */
    public function test_no_se_ofrece_la_etapa_en_la_que_ya_esta(): void
    {
        $siguientes = array_map(
            fn (EstadoUnidad $e) => $e->value,
            EstadoUnidad::EnAduana->siguientes(),
        );

        $this->assertNotEmpty($siguientes);
        $this->assertNotContains(EstadoUnidad::EnAduana->value, $siguientes);
    }
}
