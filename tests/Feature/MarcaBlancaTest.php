<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\Empresa;
use App\Models\Role;
use App\Models\User;
use App\Support\AvatarDeIniciales;
use App\Support\MarcaDelCliente;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Marca blanca: el concesionario entra a su panel y ve su nombre, su logo y su
 * color, no los de Lotea.
 *
 * El test que más importa aquí es el de la fuga: si la marca de un cliente se
 * quedara pegada y el siguiente viera el logo del anterior, el sistema estaría
 * enseñándole a un concesionario quién más lo usa.
 */
class MarcaBlancaTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $valle;

    protected Empresa $norte;

    protected User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        MarcaDelCliente::olvidar();

        $this->valle = (new CrearEmpresa)->ejecutar([
            'nombre' => 'Autos del Valle, S.A.',
            'nombre_comercial' => 'Autos del Valle',
            'color_primario' => '#0ea5e9',
        ]);

        $this->norte = (new CrearEmpresa)->ejecutar([
            'nombre' => 'Vehículos del Norte',
            'nombre_comercial' => 'Autos del Norte',
            'color_primario' => '#16a34a',
        ]);

        // Un mismo usuario en los dos concesionarios: así el test puede pasar
        // de uno a otro sin volver a autenticarse, que es justo el escenario
        // donde una marca pegada se notaría.
        $this->usuario = User::factory()->create();
        $this->usuario->empresas()->attach([$this->valle->id, $this->norte->id]);

        foreach ([$this->valle, $this->norte] as $empresa) {
            Tenancy::comoEmpresa($empresa, fn () => $this->usuario->assignRole(
                Role::findByName('dueno', 'web')
            ));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        MarcaDelCliente::olvidar();

        parent::tearDown();
    }

    /**
     * No se llama a olvidar() aquí a propósito: así dos requests seguidos
     * comparten el estado estático igual que en un proceso reusado, que es la
     * única forma de que el test de la fuga pruebe algo.
     */
    protected function verPanelDe(Empresa $empresa): string
    {
        return $this->actingAs($this->usuario)
            ->get("/app/{$empresa->slug}")
            ->assertSuccessful()
            ->getContent();
    }

    /**
     * El tono 500 que de verdad se aplica.
     *
     * Se toma la última declaración, no la primera: la paleta del cliente se
     * emite después de la de Filament justamente para ganar por cascada, que
     * es lo que hará el navegador.
     */
    protected function tonoPrincipal(string $html): ?string
    {
        preg_match_all('/--primary-500:\s*([^;]+);/', $html, $coincidencias);

        return $coincidencias[1] ? end($coincidencias[1]) : null;
    }

    public function test_el_panel_saluda_con_el_nombre_del_concesionario(): void
    {
        $html = $this->verPanelDe($this->valle);

        $this->assertStringContainsString('Autos del Valle', $html);
    }

    public function test_cada_concesionario_pinta_su_panel_con_su_color(): void
    {
        $delValle = $this->tonoPrincipal($this->verPanelDe($this->valle));
        $delNorte = $this->tonoPrincipal($this->verPanelDe($this->norte));

        $this->assertNotNull($delValle, 'El panel no emitió la variable del color primario.');
        $this->assertNotSame($delValle, $delNorte);
    }

    /**
     * La fuga. Dos paneles seguidos en el mismo proceso: el segundo no puede
     * heredar la marca del primero.
     *
     * Se mira el <title> y no todo el HTML: este usuario pertenece a los dos
     * concesionarios, así que el selector de empresa nombra a ambos con toda
     * razón. Lo que no puede repetirse es de quién es el panel.
     */
    public function test_la_marca_de_un_cliente_no_se_queda_pegada_para_el_siguiente(): void
    {
        $this->verPanelDe($this->valle);

        $html = $this->verPanelDe($this->norte);

        $this->assertSame('Autos del Norte', $this->titulo($html));
        $this->assertNotSame(
            $this->tonoPrincipal($this->verPanelDe($this->valle)),
            $this->tonoPrincipal($this->verPanelDe($this->norte)),
        );
    }

    /** El nombre del concesionario tal como sale en la pestaña del navegador. */
    protected function titulo(string $html): ?string
    {
        preg_match('/<title>(.*?)<\/title>/s', $html, $coincidencias);

        return isset($coincidencias[1])
            ? trim(last(explode('-', $coincidencias[1])))
            : null;
    }

    public function test_el_logo_del_cliente_aparece_en_su_panel(): void
    {
        $this->subirLogo($this->valle);

        $this->assertStringContainsString(
            $this->valle->fresh()->logo_url,
            $this->verPanelDe($this->valle),
        );
    }

    /**
     * Un archivo borrado a mano deja una imagen rota, y se acepta.
     *
     * Comprobar que el archivo está antes de escribir su URL cuesta un viaje al
     * almacenamiento —unos 300 ms contra R2, en cada visita, por cada imagen de
     * marca—, y el panel no puede pagar eso para cubrir un caso que solo pasa
     * si alguien borra archivos por fuera del sistema.
     *
     * Lo que sí se cuida es que la página no se caiga por ello.
     */
    public function test_un_logo_borrado_a_mano_no_tumba_el_panel(): void
    {
        $this->valle->update(['logo_path' => 'marcas/borrado-a-mano.png']);

        $this->assertNotNull($this->valle->fresh()->logo_url);
        $this->verPanelDe($this->valle);
    }

    public function test_sin_logo_oscuro_se_usa_el_normal(): void
    {
        $this->subirLogo($this->valle);

        $empresa = $this->valle->fresh();

        $this->assertSame($empresa->logo_url, $empresa->logo_oscuro_url);
    }

    /**
     * De este hex sale la paleta del panel. Con un valor que no se pueda
     * convertir, el cliente se quedaría sin panel, así que se cae al ámbar.
     */
    public function test_un_color_con_basura_no_tumba_el_panel(): void
    {
        $this->valle->update(['color_primario' => 'azul bonito']);

        $this->assertSame(Empresa::COLOR_POR_DEFECTO, $this->valle->fresh()->color_de_marca);
        $this->verPanelDe($this->valle);
    }

    /** La columna es NOT NULL con el ámbar por default; vaciarla sí es posible. */
    public function test_sin_color_propio_usa_el_de_lotea(): void
    {
        $this->valle->update(['color_primario' => '']);

        $this->assertSame(Empresa::COLOR_POR_DEFECTO, $this->valle->fresh()->color_de_marca);
    }

    public function test_sin_logo_se_muestran_las_iniciales(): void
    {
        $this->assertSame('AV', $this->valle->iniciales);
        $this->assertNull($this->valle->logo_url);
    }

    public function test_el_portal_publico_lleva_el_favicon_del_cliente(): void
    {
        $favicon = UploadedFile::fake()->image('favicon.png', 64, 64);
        $ruta = $favicon->store('marcas', 'public');

        $this->valle->update(['favicon_path' => $ruta]);

        $this->get("/v/{$this->valle->slug}")
            ->assertSuccessful()
            ->assertSee('rel="icon"', escape: false)
            ->assertSee($this->valle->fresh()->favicon_url, escape: false);
    }

    /** El panel de Lotea es de Lotea: no se pinta con la marca de nadie. */
    public function test_el_panel_central_no_toma_la_marca_de_un_cliente(): void
    {
        MarcaDelCliente::olvidar();

        $operador = User::factory()->create(['es_operador' => true]);

        $html = $this->actingAs($operador)->get('/central')->assertSuccessful()->getContent();

        $this->assertStringContainsString('Lotea', $html);
        $this->assertStringNotContainsString('Autos del Valle', $html);
    }

    /**
     * Filament trae ui-avatars.com por defecto, que se lleva el nombre de cada
     * usuario y de cada concesionario a un servidor ajeno en cada carga.
     */
    public function test_el_panel_no_manda_los_nombres_a_un_servicio_externo(): void
    {
        $html = $this->verPanelDe($this->valle);

        $this->assertStringNotContainsString('ui-avatars.com', $html);
    }

    public function test_el_avatar_de_iniciales_se_dibuja_con_el_color_del_cliente(): void
    {
        $this->verPanelDe($this->valle);

        $svg = base64_decode(str(app(AvatarDeIniciales::class)->get($this->usuario))
            ->after('base64,')
            ->value());

        $this->assertStringContainsString($this->valle->color_de_marca, $svg);
    }

    /**
     * Los nombres del sistema traen paréntesis y puntos: «Jeferson (dueño)»,
     * «Autos del Valle, S.A.».
     */
    #[DataProvider('nombresReales')]
    public function test_las_iniciales_salen_limpias(string $nombre, string $esperadas): void
    {
        $usuario = User::factory()->create(['name' => $nombre]);

        $svg = base64_decode(str(app(AvatarDeIniciales::class)->get($usuario))->after('base64,')->value());

        $this->assertStringContainsString(">{$esperadas}</text>", $svg);
    }

    /** @return array<string, array{string, string}> */
    public static function nombresReales(): array
    {
        return [
            'con el rol entre paréntesis' => ['Jeferson (dueño)', 'JD'],
            'nombre y apellido' => ['Carlos Ramírez', 'CR'],
            'una sola palabra' => ['Ana', 'AN'],
            'nombre comercial de la empresa' => ['Autos del Valle', 'AV'],
            'con acento' => ['Álvaro Ñuñez', 'ÁÑ'],
            'solo símbolos' => ['(...)', '?'],
        ];
    }

    protected function subirLogo(Empresa $empresa): void
    {
        $logo = UploadedFile::fake()->image('logo.png', 240, 80);

        $empresa->update(['logo_path' => $logo->store('marcas', 'public')]);
    }
}
