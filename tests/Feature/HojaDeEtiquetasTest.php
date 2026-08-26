<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\Empresa;
use App\Models\Role;
use App\Models\Unidad;
use App\Models\User;
use App\Support\AlmacenDeArchivos;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * La hoja de etiquetas para el parabrisas.
 *
 * Lo que se cuida aquí es lo que no se ve en pantalla: cada URL de marca le
 * pregunta al disco si el archivo está, y en producción ese disco es un FTP en
 * otro servidor. La vista pedía el logo dos veces por etiqueta, asÍ que una
 * hoja de cuarenta unidades eran más de cien viajes de red antes de responder.
 */
class HojaDeEtiquetasTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Gómez']);

        $this->usuario = User::factory()->create();
        $this->usuario->empresas()->attach($this->empresa);

        Tenancy::comoEmpresa($this->empresa, function () {
            Permission::findOrCreate('ViewAny:Unidad', 'web');
            Permission::findOrCreate('View:Unidad', 'web');
            Role::findByName('dueno', 'web')->syncPermissions(Permission::all());
            $this->usuario->assignRole('dueno');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Deja un logo de verdad en el disco y devuelve su ruta. */
    protected function ponerLogo(): string
    {
        $ruta = 'importadora-gomez/marca/logo.svg';

        AlmacenDeArchivos::disco()->put($ruta, '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $this->empresa->forceFill(['logo_claro_path' => $ruta])->save();

        return $ruta;
    }

    /**
     * La prueba está en borrar el archivo entre las dos llamadas: si la segunda
     * volviera a preguntarle al disco, ya no lo encontraría.
     */
    public function test_la_url_del_logo_no_se_le_pregunta_al_disco_dos_veces(): void
    {
        $ruta = $this->ponerLogo();

        $primera = $this->empresa->logo_url;
        $this->assertNotNull($primera, 'No resolvió el logo ni la primera vez.');

        AlmacenDeArchivos::disco()->delete($ruta);

        $this->assertSame(
            $primera,
            $this->empresa->logo_url,
            'Volvió a consultar el disco: en producción eso es un viaje al FTP por cada uso.',
        );
    }

    /** Pero una imagen nueva sí se tiene que ver: la memoria no puede ser eterna. */
    public function test_al_cambiar_la_imagen_la_url_se_resuelve_de_nuevo(): void
    {
        $this->ponerLogo();
        $vieja = $this->empresa->logo_url;

        $otra = 'importadora-gomez/marca/logo-nuevo.svg';
        AlmacenDeArchivos::disco()->put($otra, '<svg xmlns="http://www.w3.org/2000/svg"/>');

        $this->travel(2)->seconds();
        $this->empresa->forceFill(['logo_claro_path' => $otra])->save();

        $this->assertNotSame($vieja, $this->empresa->logo_url);
    }

    public function test_la_hoja_sale_con_una_etiqueta_por_unidad(): void
    {
        Tenancy::comoEmpresa($this->empresa, fn () => Unidad::factory()->count(3)->create());

        $respuesta = $this->actingAs($this->usuario)
            ->get("/app/{$this->empresa->slug}/unidades/etiquetas");

        $respuesta->assertSuccessful();

        $this->assertSame(
            3,
            substr_count($respuesta->getContent(), 'class="etiqueta'),
            'No salió una etiqueta por unidad.',
        );
    }

    /** El logo va como un solo recurso: el navegador lo pide una vez y lo reusa. */
    public function test_el_logo_es_el_mismo_enlace_en_todas_las_etiquetas(): void
    {
        $this->ponerLogo();

        Tenancy::comoEmpresa($this->empresa, fn () => Unidad::factory()->count(4)->create());

        $html = $this->actingAs($this->usuario)
            ->get("/app/{$this->empresa->slug}/unidades/etiquetas")
            ->getContent();

        preg_match_all('/<img src="([^"]+)" alt="Importadora Gómez"/', $html, $coincidencias);

        $this->assertCount(4, $coincidencias[1], 'El logo no salió en las cuatro etiquetas.');
        $this->assertCount(1, array_unique($coincidencias[1]), 'Cada etiqueta pide un enlace distinto.');
    }

    /** El QR va incrustado: una hoja que se imprime no puede depender de la red. */
    public function test_los_codigos_van_incrustados_y_no_como_peticion(): void
    {
        Tenancy::comoEmpresa($this->empresa, fn () => Unidad::factory()->create());

        $this->actingAs($this->usuario)
            ->get("/app/{$this->empresa->slug}/unidades/etiquetas")
            ->assertSee('src="data:image/svg+xml;base64,', false);
    }
}
