<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Marca;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Si alguno de estos tests se pone en rojo, el producto está roto: significa
 * que un concesionario puede ver el inventario o los costos de otro.
 *
 * No los borres ni los marques como skipped para hacer pasar un build.
 */
class AislamientoEntreEmpresasTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $unaEmpresa;

    protected Empresa $otraEmpresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unaEmpresa = Empresa::factory()->create(['nombre' => 'Autos del Valle']);
        $this->otraEmpresa = Empresa::factory()->create(['nombre' => 'Importadora Zona 11']);

        Tenancy::comoEmpresa($this->unaEmpresa, fn () => Sucursal::create([
            'codigo' => 'PRIN', 'nombre' => 'Patio Roosevelt',
        ]));

        Tenancy::comoEmpresa($this->otraEmpresa, fn () => Sucursal::create([
            'codigo' => 'PRIN', 'nombre' => 'Patio Zona 11',
        ]));
    }

    public function test_una_empresa_solo_ve_sus_propios_registros(): void
    {
        Tenancy::usar($this->unaEmpresa);

        $this->assertSame(1, Sucursal::count());
        $this->assertSame('Patio Roosevelt', Sucursal::first()->nombre);
    }

    public function test_no_puede_leer_por_id_un_registro_de_otra_empresa(): void
    {
        $ajena = Tenancy::comoEmpresa($this->otraEmpresa, fn () => Sucursal::first());

        Tenancy::usar($this->unaEmpresa);

        $this->assertNull(Sucursal::find($ajena->id));
        $this->assertSame(0, Sucursal::whereKey($ajena->id)->count());
    }

    public function test_al_crear_se_asigna_sola_la_empresa_activa(): void
    {
        Tenancy::usar($this->otraEmpresa);

        $proveedor = Proveedor::create(['tipo' => 'naviera', 'nombre' => 'Seaboard Marine']);

        $this->assertSame($this->otraEmpresa->id, $proveedor->empresa_id);
    }

    public function test_crear_sin_empresa_activa_revienta(): void
    {
        Tenancy::olvidar();

        $this->expectException(RuntimeException::class);

        Proveedor::create(['tipo' => 'naviera', 'nombre' => 'Sin dueño']);
    }

    public function test_no_se_puede_mover_un_registro_a_otra_empresa(): void
    {
        Tenancy::usar($this->unaEmpresa);
        $sucursal = Sucursal::first();

        $this->expectException(RuntimeException::class);

        $sucursal->update(['empresa_id' => $this->otraEmpresa->id]);
    }

    public function test_el_catalogo_compartido_muestra_lo_global_y_lo_propio_pero_no_lo_ajeno(): void
    {
        Marca::withoutGlobalScopes()->create(['empresa_id' => null, 'nombre' => 'Toyota', 'slug' => 'toyota']);

        Tenancy::comoEmpresa($this->unaEmpresa, fn () => Marca::create(['nombre' => 'JAC', 'slug' => 'jac']));
        Tenancy::comoEmpresa($this->otraEmpresa, fn () => Marca::create(['nombre' => 'Foton', 'slug' => 'foton']));

        Tenancy::usar($this->unaEmpresa);

        $nombres = Marca::pluck('nombre')->sort()->values()->all();

        $this->assertSame(['JAC', 'Toyota'], $nombres);
    }

    public function test_el_escape_explicito_si_ve_todas_las_empresas(): void
    {
        Tenancy::usar($this->unaEmpresa);

        $this->assertSame(1, Sucursal::count());
        $this->assertSame(2, Tenancy::sinFiltro(fn () => Sucursal::count()));
    }

    public function test_un_usuario_no_puede_entrar_a_una_empresa_que_no_es_suya(): void
    {
        $usuario = User::factory()->create();
        $usuario->empresas()->attach($this->unaEmpresa);

        $this->assertTrue($usuario->canAccessTenant($this->unaEmpresa));
        $this->assertFalse($usuario->canAccessTenant($this->otraEmpresa));
    }

    public function test_el_selector_del_panel_solo_ofrece_las_empresas_del_usuario(): void
    {
        $usuario = User::factory()->create();
        $usuario->empresas()->attach($this->unaEmpresa);

        $panel = Filament::getPanel('admin');

        $this->assertSame(
            [$this->unaEmpresa->id],
            $usuario->getTenants($panel)->pluck('id')->all(),
        );
    }

    public function test_una_empresa_desactivada_no_aparece_en_el_selector(): void
    {
        $usuario = User::factory()->create();
        $usuario->empresas()->attach([$this->unaEmpresa->id, $this->otraEmpresa->id]);
        $this->otraEmpresa->update(['activa' => false]);

        $panel = Filament::getPanel('admin');

        $this->assertSame([$this->unaEmpresa->id], $usuario->getTenants($panel)->pluck('id')->all());
    }
}
