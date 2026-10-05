<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
use App\Filament\Pages\TopVendedores;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Support\Tenancy;
use Database\Seeders\PermisosDeShieldSeeder;
use Database\Seeders\PermisosPropiosSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La pantalla de quién vende.
 *
 * Lo que más importa probar no es que pinte la tabla, sino quién la puede
 * abrir: un ranking dice de un vistazo cuánto cobró cada vendedor, y eso es
 * justo lo que no debe ver otro vendedor.
 */
class PanelDeVendedoresTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        (new PermisosDeShieldSeeder)->run();
        (new PermisosPropiosSeeder)->run();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        Tenancy::usar($this->empresa);
    }

    protected function conRol(string $nombre, string $rol): User
    {
        $usuario = User::factory()->create(['name' => $nombre]);
        $usuario->empresas()->attach($this->empresa->getKey());
        $usuario->assignRole($rol);

        return $usuario->fresh();
    }

    protected function entrar(User $usuario): User
    {
        $this->actingAs($usuario);
        Filament::setTenant($this->empresa);

        return $usuario;
    }

    public function test_el_dueno_puede_abrirla(): void
    {
        $this->entrar($this->conRol('Wagner', 'dueno'));

        $this->assertTrue(TopVendedores::canAccess());
    }

    /** El ranking enseña lo que cobró cada quien: no es para los vendedores. */
    public function test_el_vendedor_no_puede_abrirla(): void
    {
        $this->entrar($this->conRol('Ana', 'vendedor'));

        $this->assertFalse(TopVendedores::canAccess());
    }

    public function test_muestra_a_cada_vendedor_con_lo_suyo(): void
    {
        $ana = $this->conRol('Ana', 'vendedor');
        $this->conRol('Bruno', 'vendedor');

        Venta::create([
            'unidad_id' => Unidad::factory()->create(['estado' => EstadoUnidad::Publicada])->getKey(),
            'cliente_id' => Cliente::factory()->create()->getKey(),
            'vendedor_id' => $ana->getKey(),
            'numero' => 'V-1',
            'estado' => 'cerrada',
            'fecha' => today(),
            'precio_venta' => '95000.00',
            'precio_final' => '95000.00',
            'comision_monto' => '1800.00',
        ]);

        $this->entrar($this->conRol('Wagner', 'dueno'));

        Livewire::test(TopVendedores::class)
            ->assertOk()
            ->assertSee('Ana')
            ->assertSee('Bruno')
            ->assertSee('95,000.00')
            ->assertSee('1,800.00');
    }

    public function test_el_selector_de_periodo_cambia_lo_que_se_cuenta(): void
    {
        $ana = $this->conRol('Ana', 'vendedor');

        Venta::create([
            'unidad_id' => Unidad::factory()->create(['estado' => EstadoUnidad::Publicada])->getKey(),
            'cliente_id' => Cliente::factory()->create()->getKey(),
            'vendedor_id' => $ana->getKey(),
            'numero' => 'V-2',
            'estado' => 'cerrada',
            'fecha' => today()->subMonthNoOverflow()->startOfMonth()->addDay(),
            'precio_venta' => '95000.00',
            'precio_final' => '95000.00',
        ]);

        $this->entrar($this->conRol('Wagner', 'dueno'));

        $pagina = Livewire::test(TopVendedores::class);

        $this->assertSame(0, $pagina->instance()->getTotales()['carros']);

        $pagina->set('periodo', 'mes_pasado');

        $this->assertSame(1, $pagina->instance()->getTotales()['carros']);
    }
}
