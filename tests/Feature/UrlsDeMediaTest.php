<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\Unidad;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las fotos del portal no pueden llevar el dominio de Lotea.
 *
 * El cliente publica su portal en su propio dominio; si el src de las fotos de
 * sus carros apuntara a lotea.gt, la marca blanca se cae sola.
 *
 * Este test configura el disco con un dominio absoluto a propósito, que es como
 * está en producción. Con Storage::fake el disco ya devuelve rutas relativas y
 * el test pasaría aunque el generador estuviera mal.
 */
class UrlsDeMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.disks.public.url' => 'https://lotea.gt/storage']);

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']));
    }

    public function test_la_url_de_una_foto_es_relativa(): void
    {
        $unidad = Unidad::factory()->publicada()->create(['precio_lista' => 90000]);

        $url = $unidad->getFirstMediaUrl('fotos');

        $this->assertStringStartsWith('/storage/', $url);
        $this->assertStringNotContainsString('lotea.gt', $url);
    }

    /**
     * Con version_urls activo, medialibrary cuelga ?v= para invalidar caché.
     * Viene apagado, pero si se enciende la ruta relativa no puede comérselo.
     */
    public function test_al_recortar_el_dominio_no_se_pierde_la_version(): void
    {
        config(['media-library.version_urls' => true]);

        $unidad = Unidad::factory()->publicada()->create(['precio_lista' => 90000]);

        $url = $unidad->getFirstMediaUrl('fotos');

        $this->assertStringStartsWith('/storage/', $url);
        $this->assertStringContainsString('?v=', $url);
    }
}
