<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\CategoriaCosto;
use App\Models\Sucursal;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AltaDeEmpresaTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_empresa_nueva_nace_operable(): void
    {
        $empresa = (new CrearEmpresa)->ejecutar([
            'nombre' => 'Autos del Valle, S.A.',
            'nombre_comercial' => 'Autos del Valle',
        ], 'Patio Roosevelt');

        Tenancy::usar($empresa);

        $this->assertSame('autos-del-valle', $empresa->slug);
        $this->assertSame(1, Sucursal::where('es_principal', true)->count());
        $this->assertSame(count(CrearEmpresa::CATEGORIAS_BASE), CategoriaCosto::count());
        $this->assertTrue(CategoriaCosto::where('codigo', 'iprima')->first()->afecta_costo);
        $this->assertFalse(CategoriaCosto::where('codigo', 'comision_vendedor')->first()->afecta_costo);
        $this->assertTrue(CategoriaCosto::where('codigo', 'flete_maritimo')->first()->prorrateable);
    }

    public function test_las_categorias_de_una_empresa_no_se_mezclan_con_las_de_otra(): void
    {
        $accion = new CrearEmpresa;
        $una = $accion->ejecutar(['nombre' => 'Concesionario Uno']);
        $otra = $accion->ejecutar(['nombre' => 'Concesionario Dos']);

        Tenancy::usar($una);
        $this->assertSame(count(CrearEmpresa::CATEGORIAS_BASE), CategoriaCosto::count());

        Tenancy::usar($otra);
        $this->assertSame(count(CrearEmpresa::CATEGORIAS_BASE), CategoriaCosto::count());

        $this->assertSame(
            count(CrearEmpresa::CATEGORIAS_BASE) * 2,
            Tenancy::sinFiltro(fn () => CategoriaCosto::count()),
        );
    }
}
