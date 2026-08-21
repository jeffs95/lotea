<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Filament\Pages\MiMarca;
use App\Models\Empresa;
use App\Models\Role;
use App\Models\User;
use App\Support\MarcaDelCliente;
use App\Support\Tenancy;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * El dueño del concesionario cambiando su propio logo y su color.
 *
 * Va con permiso propio porque no es una pantalla para cualquiera: un vendedor
 * no tiene por qué poder cambiarle los colores a la empresa. Y sobre todo, de
 * aquí no puede salir un cambio en la marca de otro cliente.
 */
class MiMarcaTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $valle;

    protected Empresa $norte;

    protected User $dueno;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        MarcaDelCliente::olvidar();

        Permission::findOrCreate(MiMarca::PERMISO, 'web');

        $this->valle = (new CrearEmpresa)->ejecutar([
            'nombre' => 'Autos del Valle',
            'color_primario' => '#0ea5e9',
        ]);

        $this->norte = (new CrearEmpresa)->ejecutar([
            'nombre' => 'Autos del Norte',
            'color_primario' => '#16a34a',
        ]);

        $this->dueno = User::factory()->create();
        $this->dueno->empresas()->attach($this->valle);

        Tenancy::comoEmpresa($this->valle, fn () => $this->dueno->assignRole(
            Role::findByName('dueno', 'web')
        ));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        MarcaDelCliente::olvidar();

        parent::tearDown();
    }

    protected function comoDueno(): self
    {
        $this->actingAs($this->dueno);
        Tenancy::usar($this->valle);
        Filament::setTenant($this->valle);

        return $this;
    }

    public function test_el_dueno_nace_con_el_permiso(): void
    {
        Tenancy::usar($this->valle);

        $this->assertTrue($this->dueno->can(MiMarca::PERMISO));
    }

    public function test_el_dueno_ve_la_pantalla(): void
    {
        $this->comoDueno();

        $this->assertTrue(MiMarca::canAccess());

        $this->get(MiMarca::getUrl(tenant: $this->valle))->assertSuccessful();
    }

    /** Un vendedor no le cambia los colores a la empresa. */
    public function test_sin_el_permiso_no_hay_pantalla(): void
    {
        $vendedor = User::factory()->create();
        $vendedor->empresas()->attach($this->valle);

        Tenancy::comoEmpresa($this->valle, fn () => $vendedor->assignRole(
            Role::findByName('vendedor', 'web')
        ));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($vendedor);
        Tenancy::usar($this->valle);
        Filament::setTenant($this->valle);

        $this->assertFalse(MiMarca::canAccess());

        $this->get(MiMarca::getUrl(tenant: $this->valle))->assertForbidden();
    }

    public function test_guardar_le_cambia_el_color_a_su_empresa(): void
    {
        $this->comoDueno();

        Livewire::test(MiMarca::class)
            ->fillForm(['color_primario' => '#7c3aed'])
            ->call('guardar')
            ->assertHasNoFormErrors();

        $this->assertSame('#7c3aed', $this->valle->fresh()->color_de_marca);
    }

    public function test_guardar_le_sube_el_logo(): void
    {
        $this->comoDueno();

        Livewire::test(MiMarca::class)
            ->fillForm(['logo_path' => [UploadedFile::fake()->image('logo.png', 240, 80)]])
            ->call('guardar')
            ->assertHasNoFormErrors();

        $empresa = $this->valle->fresh();

        $this->assertNotNull($empresa->logo_path);
        Storage::disk('public')->assertExists($empresa->logo_path);
        $this->assertNotNull($empresa->logo_url);
    }

    /**
     * El color se valida porque de él sale la paleta del panel: guardar basura
     * dejaría al cliente sin panel hasta que alguien entre a la base.
     */
    public function test_no_deja_guardar_un_color_que_no_es_color(): void
    {
        $this->comoDueno();

        Livewire::test(MiMarca::class)
            ->fillForm(['color_primario' => 'azul bonito'])
            ->call('guardar')
            ->assertHasFormErrors(['color_primario']);

        $this->assertSame('#0ea5e9', $this->valle->fresh()->color_de_marca);
    }

    /**
     * Lo que de verdad importa: la empresa sale del tenant del panel, así que
     * no hay campo que tocar para escribirle la marca a otro concesionario.
     */
    public function test_no_puede_cambiarle_la_marca_a_otro_concesionario(): void
    {
        $this->comoDueno();

        Livewire::test(MiMarca::class)
            ->fillForm([
                'color_primario' => '#ffffff',
                'empresa_id' => $this->norte->id,
                'id' => $this->norte->id,
            ])
            ->call('guardar');

        $this->assertSame('#16a34a', $this->norte->fresh()->color_de_marca);
        $this->assertSame('#ffffff', $this->valle->fresh()->color_de_marca);
    }

    public function test_el_panel_se_repinta_con_el_color_nuevo(): void
    {
        $this->comoDueno();

        Livewire::test(MiMarca::class)
            ->fillForm(['color_primario' => '#dc2626'])
            ->call('guardar');

        MarcaDelCliente::olvidar();

        $html = $this->actingAs($this->dueno)
            ->get("/app/{$this->valle->slug}")
            ->assertSuccessful()
            ->getContent();

        preg_match_all('/--primary-500:\s*([^;]+);/', $html, $tonos);

        $this->assertSame(
            Color::hex('#dc2626')[500],
            end($tonos[1]),
        );
    }
}
