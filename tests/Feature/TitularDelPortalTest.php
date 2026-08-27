<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\Empresa;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El titular de la portada, en manos de cada concesionario.
 *
 * Era un texto fijo en la plantilla, igual para todos, y hablaba del proceso:
 * traer las unidades de subasta y prepararlas en el taller. Está bien contado,
 * pero quien entra a la página quiere ver el carro y su precio; el proceso se
 * cuenta después, cuando ya está interesado.
 *
 * Y sobre todo es el mensaje de venta de cada patio, no de Lotea: uno vende
 * motos de trabajo y otro camionetas de lujo.
 */
class TitularDelPortalTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        Tenancy::usar($this->empresa);
    }

    /** Sin escribir nada, la portada no queda vacía. */
    public function test_sin_configurar_sale_el_titular_de_casa(): void
    {
        $this->get("/v/{$this->empresa->slug}")
            ->assertSuccessful()
            ->assertSee(Empresa::TITULAR_POR_DEFECTO)
            ->assertSee(Empresa::SUBTITULO_POR_DEFECTO);
    }

    public function test_el_concesionario_pone_el_suyo(): void
    {
        $this->empresa->update([
            'titular_portal' => 'Camionetas 4x4 listas para el interior.',
            'subtitulo_portal' => 'Financiamiento propio, sin banco.',
        ]);

        $this->get("/v/{$this->empresa->slug}")
            ->assertSuccessful()
            ->assertSee('Camionetas 4x4 listas para el interior.')
            ->assertSee('Financiamiento propio, sin banco.')
            ->assertDontSee(Empresa::TITULAR_POR_DEFECTO);
    }

    /** Uno puede querer cambiar solo el titular y dejar el resto. */
    public function test_se_puede_cambiar_uno_y_no_el_otro(): void
    {
        $this->empresa->update(['titular_portal' => 'Motos de trabajo, entrega el mismo día.']);

        $this->get("/v/{$this->empresa->slug}")
            ->assertSee('Motos de trabajo, entrega el mismo día.')
            ->assertSee(Empresa::SUBTITULO_POR_DEFECTO);
    }

    /** Un titular en blanco no deja el hueco: vuelve al de casa. */
    public function test_dejarlo_vacio_no_deja_la_portada_muda(): void
    {
        $this->empresa->update(['titular_portal' => '   ']);

        $this->assertSame(
            Empresa::TITULAR_POR_DEFECTO,
            $this->empresa->fresh()->titular_del_portal,
        );
    }

    /** Y el texto viejo, el del proceso, ya no aparece en ninguna parte. */
    public function test_el_texto_viejo_ya_no_esta(): void
    {
        $respuesta = $this->get("/v/{$this->empresa->slug}")->assertSuccessful();

        $respuesta->assertDontSee('Carros de importación,');
        $respuesta->assertDontSee('Traemos las unidades directo de subasta');
    }
}
