<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Los roles viven por empresa. Si se compartieran, el cliente que edita su rol
 * de "vendedor" le estaría cambiando los permisos al vendedor de otro
 * concesionario.
 */
class RolesPorEmpresaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_empresa_tiene_su_propio_juego_de_roles(): void
    {
        $accion = new CrearEmpresa;
        $una = $accion->ejecutar(['nombre' => 'Concesionario Uno']);
        $otra = $accion->ejecutar(['nombre' => 'Concesionario Dos']);

        $rolesUna = Role::where('empresa_id', $una->id)->pluck('name')->sort()->values();
        $rolesOtra = Role::where('empresa_id', $otra->id)->pluck('name')->sort()->values();

        $this->assertCount(count(CrearEmpresa::ROLES_BASE), $rolesUna);
        $this->assertEquals($rolesUna, $rolesOtra);
        $this->assertSame(0, Role::whereNull('empresa_id')->count());
    }

    /**
     * Ningún rol puede existir sin empresa.
     *
     * Un rol global con todos los permisos ve los datos de todos los
     * concesionarios; Shield creaba dos así (super_admin y panel_user) y
     * quedaron desactivados en su config.
     */
    public function test_no_existe_ningun_rol_global(): void
    {
        (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);

        $this->assertSame(0, Role::whereNull('empresa_id')->count());
        $this->assertFalse(config('filament-shield.super_admin.enabled'));
        $this->assertFalse(config('filament-shield.panel_user.enabled'));
    }

    public function test_un_rol_en_una_empresa_no_da_permisos_en_la_otra(): void
    {
        $accion = new CrearEmpresa;
        $una = $accion->ejecutar(['nombre' => 'Concesionario Uno']);
        $otra = $accion->ejecutar(['nombre' => 'Concesionario Dos']);

        $usuario = User::factory()->create();
        $usuario->empresas()->attach([$una->id, $otra->id]);

        Tenancy::comoEmpresa($una, fn () => $usuario->assignRole('dueno'));

        Tenancy::usar($una);
        $this->assertTrue($usuario->fresh()->hasRole('dueno'));

        Tenancy::usar($otra);
        $this->assertFalse($usuario->fresh()->hasRole('dueno'));
    }
}
