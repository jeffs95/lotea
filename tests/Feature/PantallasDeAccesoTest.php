<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las dos pantallas de entrada.
 *
 * Son la primera cosa que ve un cliente al que se le acaba de cobrar una
 * licencia, y también el punto donde más se confundía la gente: dos rutas
 * parecidas, la misma tarjeta gris en las dos. Lo que se prueba aquí es que se
 * distingan y que cada una diga lo que hace falta.
 */
class PantallasDeAccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_entrada_del_concesionario_muestra_su_portada(): void
    {
        $this->get('/app/login')
            ->assertSuccessful()
            ->assertSee('Su patio completo')
            ->assertSee('Entre a su cuenta');
    }

    public function test_la_entrada_de_lotea_se_ve_distinta(): void
    {
        $this->get('/central/login')
            ->assertSuccessful()
            ->assertSee('Uso interno')
            ->assertSee('Entre a la central')
            ->assertDontSee('Su patio completo');
    }

    /** Quien llega equivocado al central tiene que enterarse antes de insistir. */
    public function test_el_central_dice_donde_entran_los_concesionarios(): void
    {
        $this->get('/central/login')->assertSee('la suya es /app');
    }

    /**
     * No hay recuperación de contraseña, así que un vendedor que la olvide se
     * queda sin salida. Al menos que sepa a quién pedírsela.
     */
    public function test_la_entrada_del_cliente_dice_que_hacer_si_olvido_la_clave(): void
    {
        $this->get('/app/login')->assertSee('Pídale a quien administra su concesionario');
    }

    /**
     * En /app/login la URL todavía no dice de qué concesionario es quien
     * escribe, así que la marca es de Lotea. La del cliente aparece al entrar.
     */
    public function test_la_entrada_no_pinta_la_marca_de_ningun_cliente(): void
    {
        (new CrearEmpresa)->ejecutar([
            'nombre' => 'Importadora Gómez',
            'color_primario' => '#16a34a',
        ]);

        $respuesta = $this->get('/app/login');

        $respuesta->assertDontSee('Importadora Gómez');
        $respuesta->assertDontSee('#16a34a');
        $respuesta->assertSee(Empresa::COLOR_POR_DEFECTO);
    }
}
