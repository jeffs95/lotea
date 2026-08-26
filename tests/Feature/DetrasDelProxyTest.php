<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Que la aplicación se entienda con el proxy que tiene delante.
 *
 * En Heroku el TLS lo termina el router y a PHP la petición le llega en claro.
 * Si Laravel no confía en él, arma sus enlaces con «http://» y en un dominio con
 * HSTS —como .dev— el navegador los bloquea: el botón de entrar no hace nada y
 * en la consola aparece un «Mixed Content» que no dice qué configuración falta.
 *
 * Pasó en producción. Esto es para que no vuelva a pasar.
 */
class DetrasDelProxyTest extends TestCase
{
    public function test_los_enlaces_salen_en_https_cuando_el_proxy_lo_indica(): void
    {
        $respuesta = $this->get('/app/login', ['X-Forwarded-Proto' => 'https']);

        $respuesta->assertSuccessful();

        $this->assertStringNotContainsString(
            'http://localhost',
            $respuesta->getContent(),
            'Hay enlaces en claro: el navegador los va a bloquear por HSTS.',
        );
    }

    /** El caso exacto que dejó el acceso mudo: el endpoint de Livewire. */
    public function test_el_endpoint_de_livewire_no_queda_en_claro(): void
    {
        $html = $this->get('/app/login', ['X-Forwarded-Proto' => 'https'])->getContent();

        preg_match('/data-update-uri="([^"]+)"/', $html, $coincidencias);

        $this->assertNotEmpty($coincidencias, 'No se encontró el endpoint de Livewire en la página.');
        $this->assertStringStartsWith('https://', $coincidencias[1]);
    }

    /** Sin esto la auditoría guardaría la IP del proxy para todos. */
    public function test_se_registra_la_ip_del_visitante_y_no_la_del_proxy(): void
    {
        Route::get('/_prueba/ip', fn () => request()->ip())->middleware('web');

        $this->get('/_prueba/ip', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-For' => '203.0.113.9, 70.41.3.18',
        ])->assertSee('203.0.113.9');
    }

    /** Y en local, sin proxy delante, sigue siendo http: no se fuerza a ciegas. */
    public function test_sin_proxy_no_se_inventa_https(): void
    {
        $this->assertStringStartsWith('http://', url('/'));
    }
}
