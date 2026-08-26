<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Filament\Resources\Usuarios\Pages\CreateUsuario;
use App\Filament\Resources\Usuarios\Pages\EditUsuario;
use App\Models\Empresa;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Dar de alta un vendedor desde el panel del concesionario.
 *
 * Los roles son por empresa —«teams», en spatie— y esa relación se guarda con
 * su empresa_id. Si se escribe la tabla pivote con Eloquent en vez de con
 * assignRole(), la columna se queda vacía: revienta la base, y si no reventara
 * sería peor, porque el rol quedaría suelto y visible desde otras empresas.
 */
class AsignarRolesDesdeElPanelTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected Empresa $otraEmpresa;

    protected User $dueno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Gómez']);
        $this->otraEmpresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Norte']);

        foreach (['ViewAny', 'View', 'Create', 'Update'] as $accion) {
            Permission::findOrCreate("{$accion}:User", 'web');
        }

        $this->dueno = User::factory()->create();
        $this->dueno->empresas()->attach($this->empresa);

        Tenancy::comoEmpresa($this->empresa, function () {
            Role::findByName('dueno', 'web')->syncPermissions(Permission::all());
            $this->dueno->assignRole('dueno');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->dueno);
        Tenancy::usar($this->empresa);
        Filament::setTenant($this->empresa);
    }

    protected function rol(string $nombre, ?Empresa $empresa = null): Role
    {
        return Tenancy::comoEmpresa($empresa ?? $this->empresa, fn () => Role::findByName($nombre, 'web'));
    }

    /** El error que veía el cliente: «null value in column empresa_id». */
    public function test_se_puede_crear_un_vendedor_con_su_rol(): void
    {
        $vendedor = $this->rol('vendedor');

        Livewire::test(CreateUsuario::class)
            ->fillForm([
                'name' => 'Carlos Vendedor',
                'email' => 'carlos@ejemplo.gt',
                'password' => 'una-clave-larga',
                'roles' => [$vendedor->getKey()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $usuario = User::firstWhere('email', 'carlos@ejemplo.gt');

        $this->assertNotNull($usuario, 'No se creó el usuario.');
        $this->assertTrue(
            Tenancy::comoEmpresa($this->empresa, fn () => $usuario->fresh()->hasRole('vendedor')),
            'El usuario quedó sin su rol.',
        );
    }

    /**
     * Lo que de verdad protege esto: el vínculo lleva la empresa. Sin ella el
     * rol se vería desde cualquier otro concesionario.
     */
    public function test_el_rol_queda_atado_a_la_empresa(): void
    {
        $vendedor = $this->rol('vendedor');

        Livewire::test(CreateUsuario::class)
            ->fillForm([
                'name' => 'Carlos Vendedor',
                'email' => 'carlos@ejemplo.gt',
                'password' => 'una-clave-larga',
                'roles' => [$vendedor->getKey()],
            ])
            ->call('create');

        $usuario = User::firstWhere('email', 'carlos@ejemplo.gt');

        $vinculo = DB::table('model_has_roles')
            ->where('model_id', $usuario->getKey())
            ->where('role_id', $vendedor->getKey())
            ->first();

        $this->assertNotNull($vinculo, 'No se guardó el vínculo con el rol.');
        $this->assertSame($this->empresa->getKey(), (int) $vinculo->empresa_id);
    }

    public function test_desde_otra_empresa_no_se_ve_ese_rol(): void
    {
        $vendedor = $this->rol('vendedor');

        Livewire::test(CreateUsuario::class)
            ->fillForm([
                'name' => 'Carlos Vendedor',
                'email' => 'carlos@ejemplo.gt',
                'password' => 'una-clave-larga',
                'roles' => [$vendedor->getKey()],
            ])
            ->call('create');

        $usuario = User::firstWhere('email', 'carlos@ejemplo.gt');

        $this->assertFalse(
            Tenancy::comoEmpresa($this->otraEmpresa, fn () => $usuario->fresh()->hasRole('vendedor')),
            'El rol se ve desde otro concesionario.',
        );
    }

    public function test_editar_un_usuario_muestra_y_cambia_sus_roles(): void
    {
        $usuario = User::factory()->create();
        $usuario->empresas()->attach($this->empresa);

        Tenancy::comoEmpresa($this->empresa, fn () => $usuario->assignRole('vendedor'));

        $cajero = $this->rol('cajero');

        Livewire::test(EditUsuario::class, ['record' => $usuario->getRouteKey()])
            ->assertFormSet(fn (array $datos) => in_array(
                $this->rol('vendedor')->getKey(),
                $datos['roles'] ?? [],
            ))
            ->fillForm(['roles' => [$cajero->getKey()]])
            ->call('save')
            ->assertHasNoFormErrors();

        Tenancy::usar($this->empresa);

        $this->assertTrue($usuario->fresh()->hasRole('cajero'));
        $this->assertFalse($usuario->fresh()->hasRole('vendedor'));
    }

    /** Quitar todos los roles tiene que poder hacerse. */
    public function test_se_pueden_quitar_todos_los_roles(): void
    {
        $usuario = User::factory()->create();
        $usuario->empresas()->attach($this->empresa);

        Tenancy::comoEmpresa($this->empresa, fn () => $usuario->assignRole('vendedor'));

        Livewire::test(EditUsuario::class, ['record' => $usuario->getRouteKey()])
            ->fillForm(['roles' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        Tenancy::usar($this->empresa);

        $this->assertCount(0, $usuario->fresh()->roles);
    }
}
