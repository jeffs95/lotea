<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A dónde llega quien escribe el dominio pelado.
 *
 * La raíz la comparten dos cosas distintas: el portal público de cada cliente
 * en su propio dominio, y el dominio de Lotea, que no es portal de nadie. Un
 * redirect puesto sin mirar se llevaría por delante el sitio de los clientes,
 * así que la distinción se prueba en las dos direcciones.
 */
class PuertaDelSistemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_raiz_del_dominio_de_lotea_lleva_a_entrar(): void
    {
        $this->get('/')->assertRedirect(route('filament.admin.tenant'));
    }

    /** Y lo que había antes era un 404 sin salida. */
    public function test_el_portal_de_un_cliente_con_dominio_propio_sigue_en_su_raiz(): void
    {
        (new CrearEmpresa)->ejecutar([
            'nombre' => 'Importadora Gómez',
            'dominio' => 'gomez.test',
        ]);

        $this->get('http://gomez.test/')
            ->assertSuccessful()
            ->assertSee('Importadora Gómez', false);
    }

    /** El suspendido no se redirige: su 404 es la palanca de cobro. */
    public function test_un_cliente_suspendido_no_termina_en_el_acceso_de_lotea(): void
    {
        $empresa = (new CrearEmpresa)->ejecutar([
            'nombre' => 'Autos del Norte',
            'dominio' => 'norte.test',
        ]);

        $empresa->update(['suspendida_en' => now(), 'motivo_suspension' => 'No pagó']);

        $this->get('http://norte.test/')->assertNotFound();
    }

    /** La entrada de desarrollo sigue viva para quien no compra dominio. */
    public function test_la_ruta_de_demostracion_sigue_sirviendo_el_portal(): void
    {
        $empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Gómez']);

        $this->get("/v/{$empresa->slug}")->assertSuccessful();
    }

    /** Una URL que no existe sigue siendo un 404 y no se esconde. */
    public function test_una_ruta_inventada_en_el_dominio_de_lotea_no_se_disfraza(): void
    {
        $this->get('/vehiculos')->assertNotFound();
    }
}
