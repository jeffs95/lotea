<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Events\TenantSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Filament sabe en qué empresa está parado el usuario, pero el EmpresaScope no
 * se entera solo. Este test cuida ese cable: si se rompe, el panel seguiría
 * mostrando el selector de empresa mientras las consultas dejan de filtrar.
 */
class ContextoDeEmpresaEnElPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_al_cambiar_de_empresa_en_el_panel_se_actualiza_el_contexto(): void
    {
        $empresa = Empresa::factory()->create();
        $usuario = User::factory()->create();

        $this->assertFalse(Tenancy::hayEmpresa());

        event(new TenantSet($empresa, $usuario));

        $this->assertSame($empresa->id, Tenancy::empresaId());
    }

    public function test_los_roles_tambien_quedan_apuntando_a_esa_empresa(): void
    {
        $empresa = Empresa::factory()->create();
        $usuario = User::factory()->create();

        event(new TenantSet($empresa, $usuario));

        $this->assertSame(
            $empresa->id,
            app(PermissionRegistrar::class)->getPermissionsTeamId(),
        );
    }
}
