<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\Empresa;
use App\Models\Unidad;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DiscoQueCuenta;
use Tests\TestCase;

/**
 * Que pintar una página no le pregunte nada al almacenamiento.
 *
 * Esto fue un problema de verdad y costó encontrarlo: el portal tardaba 1,3
 * segundos en mandar el primer byte con dos vehículos en el catálogo. No eran
 * las consultas —la base era el 4% del tiempo— ni las fotos, que ya salían por
 * el CDN. Era que armar la URL del logo comprobaba antes si el archivo estaba,
 * y esa comprobación es un viaje de red: contra R2, unos 300 ms. El encabezado
 * pedía el logo y el icono, así que eran tres o cuatro viajes por visita.
 *
 * El síntoma engaña: la página se ve bien y no hay nada lento a la vista.
 */
class ElPortalNoEsperaAlAlmacenamientoTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        Tenancy::usar($this->empresa);
    }

    /** Cuenta los viajes al disco mientras corre lo que se le pase. */
    protected function contandoViajes(callable $lo): int
    {
        $real = Storage::disk('public');

        $espia = new DiscoQueCuenta($real->getDriver(), $real->getAdapter(), [
            'root' => storage_path('app/public'),
        ]);

        Storage::set('public', $espia);

        try {
            $lo();
        } finally {
            Storage::forgetDisk('public');
        }

        return $espia->viajes;
    }

    public function test_armar_la_url_del_logo_no_toca_el_almacenamiento(): void
    {
        $this->empresa->update(['logo_path' => 'marcas/logo.png']);
        $empresa = $this->empresa->fresh();

        $viajes = $this->contandoViajes(fn () => $empresa->logo_url);

        $this->assertSame(
            0,
            $viajes,
            'Armar la URL del logo va al almacenamiento. Contra R2 eso son unos 300 ms '
            .'por imagen y por visita, y el encabezado pide varias.',
        );
    }

    public function test_ni_la_del_icono_ni_la_del_logo_oscuro(): void
    {
        $this->empresa->update([
            'logo_path' => 'marcas/logo.png',
            'logo_oscuro_path' => 'marcas/logo-oscuro.png',
            'favicon_path' => 'marcas/favicon.png',
        ]);
        $empresa = $this->empresa->fresh();

        $viajes = $this->contandoViajes(function () use ($empresa) {
            $empresa->logo_url;
            $empresa->logo_oscuro_url;
            $empresa->favicon_url;
            $empresa->favicon_pestana_url;
        });

        $this->assertSame(0, $viajes, 'Alguna URL de marca sigue preguntándole al disco.');
    }

    /** Y pintar el portal entero tampoco, con vehículos y todo. */
    public function test_pintar_el_portal_no_toca_el_almacenamiento(): void
    {
        $this->empresa->update(['logo_path' => 'marcas/logo.png']);
        Unidad::factory()->count(3)->publicada()->create();

        $empresa = $this->empresa->fresh();
        view()->share('empresa', $empresa);

        $viajes = $this->contandoViajes(function () use ($empresa) {
            $this->get("/v/{$empresa->slug}")->assertSuccessful();
        });

        $this->assertSame(
            0,
            $viajes,
            'Pintar el portal va al almacenamiento. Eso es lo que lo dejaba en 1,3 segundos.',
        );
    }
}
