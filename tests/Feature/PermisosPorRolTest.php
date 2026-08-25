<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\Empresa;
use App\Models\Role;
use App\Support\PermisosPorRol;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Con qué puede trabajar cada rol el primer día.
 *
 * Antes los diez roles nacían vacíos: el dueño creaba un vendedor, el vendedor
 * entraba y no veía ni el inventario. Ahora salen configurados, y lo que hay que
 * cuidar es que la comodidad no se lleve por delante la regla de negocio más
 * importante del sistema.
 */
class PermisosPorRolTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        // Los permisos que Shield genera en una instalación de verdad.
        foreach (['Unidad', 'Venta', 'Cliente', 'Lead', 'Caja', 'OrdenTrabajo', 'PlanPago',
            'GastoCompartido', 'Empleado', 'Proveedor', 'Marca', 'CategoriaCosto',
            'Sucursal', 'User', 'Auditoria', 'TableroUnidades', 'Levantamiento',
            'CapitalEnPatio', 'UnidadesEstancadas'] as $modulo) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Restore', 'RestoreAny'] as $accion) {
                Permission::findOrCreate("{$accion}:{$modulo}", 'web');
            }
        }

        foreach (['ver_costos_unidad', 'ver_precio_minimo', 'administrar_marca'] as $propio) {
            Permission::findOrCreate($propio, 'web');
        }

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Importadora Gómez']);

        Tenancy::usar($this->empresa);
    }

    protected function rol(string $nombre): Role
    {
        return Role::findByName($nombre, 'web');
    }

    public function test_el_dueno_puede_todo_dentro_de_su_empresa(): void
    {
        $this->assertSame(
            Permission::count(),
            $this->rol('dueno')->permissions()->count(),
        );
    }

    #[DataProvider('rolesQueTrabajan')]
    public function test_cada_rol_nace_con_permisos(string $rol): void
    {
        $this->assertGreaterThan(
            0,
            $this->rol($rol)->permissions()->count(),
            "El rol «{$rol}» nació vacío: quien lo tenga entra y no ve nada.",
        );
    }

    /** @return array<string, array{string}> */
    public static function rolesQueTrabajan(): array
    {
        return collect(PermisosPorRol::roles())
            ->mapWithKeys(fn (string $rol) => [$rol => [$rol]])
            ->all();
    }

    // ── La regla que no se puede romper ─────────────────────────────────────

    /**
     * El costo del carro es lo que el patrón no le enseña a quien negocia.
     *
     * Si el vendedor sabe que costó Q86,000, el cliente lo va a saber en la
     * siguiente hora y el margen se negocia desde ahí.
     */
    #[DataProvider('rolesQueNoVenCostos')]
    public function test_quien_negocia_no_ve_el_costo(string $rol): void
    {
        $this->assertFalse(
            $this->rol($rol)->hasPermissionTo('ver_costos_unidad'),
            "El rol «{$rol}» está viendo los costos.",
        );
    }

    #[DataProvider('rolesQueNoVenCostos')]
    public function test_quien_negocia_no_ve_el_precio_minimo(string $rol): void
    {
        $this->assertFalse(
            $this->rol($rol)->hasPermissionTo('ver_precio_minimo'),
            "El rol «{$rol}» conoce el piso autorizado: puede regalarlo entero.",
        );
    }

    /** @return array<string, array{string}> */
    public static function rolesQueNoVenCostos(): array
    {
        return [
            'vendedor' => ['vendedor'],
            'cajero' => ['cajero'],
            'mecánico' => ['mecanico'],
            'recursos humanos' => ['rrhh'],
        ];
    }

    /** Y quien sí decide sobre plata tiene que verlos. */
    #[DataProvider('rolesQueDecidenPlata')]
    public function test_quien_decide_compras_si_ve_el_costo(string $rol): void
    {
        $this->assertTrue(
            $this->rol($rol)->hasPermissionTo('ver_costos_unidad'),
            "El rol «{$rol}» no puede hacer su trabajo sin ver el costo.",
        );
    }

    /** @return array<string, array{string}> */
    public static function rolesQueDecidenPlata(): array
    {
        return [
            'gerente de sucursal' => ['gerente_sucursal'],
            'comprador' => ['comprador'],
            'coordinador de importaciones' => ['coordinador_importaciones'],
            'jefe de taller' => ['jefe_taller'],
            'contador' => ['contador'],
        ];
    }

    // ── Que cada uno pueda hacer su trabajo ─────────────────────────────────

    public function test_el_vendedor_puede_vender(): void
    {
        $vendedor = $this->rol('vendedor');

        foreach (['ViewAny:Unidad', 'View:Unidad', 'Create:Venta', 'Update:Venta',
            'Create:Cliente', 'ViewAny:Lead'] as $permiso) {
            $this->assertTrue($vendedor->hasPermissionTo($permiso), "Le falta «{$permiso}».");
        }
    }

    public function test_el_cajero_maneja_caja_pero_no_vende(): void
    {
        $cajero = $this->rol('cajero');

        $this->assertTrue($cajero->hasPermissionTo('Create:Caja'));
        $this->assertTrue($cajero->hasPermissionTo('ViewAny:Venta'));
        // Cobra la venta, no la hace.
        $this->assertFalse($cajero->hasPermissionTo('Create:Venta'));
    }

    public function test_el_mecanico_solo_ve_su_trabajo(): void
    {
        $mecanico = $this->rol('mecanico');

        $this->assertTrue($mecanico->hasPermissionTo('Update:OrdenTrabajo'));
        $this->assertFalse($mecanico->hasPermissionTo('ViewAny:Caja'));
        $this->assertFalse($mecanico->hasPermissionTo('ViewAny:Venta'));
    }

    public function test_el_contador_mira_el_dinero_pero_no_opera(): void
    {
        $contador = $this->rol('contador');

        $this->assertTrue($contador->hasPermissionTo('ViewAny:Caja'));
        $this->assertTrue($contador->hasPermissionTo('ver_costos_unidad'));
        // Ve la caja; no mete ni saca dinero.
        $this->assertFalse($contador->hasPermissionTo('Create:Caja'));
        $this->assertFalse($contador->hasPermissionTo('Create:Venta'));
    }

    /** Los usuarios y la marca los administra el dueño. */
    public function test_ningun_rol_operativo_administra_usuarios_ni_la_marca(): void
    {
        foreach (PermisosPorRol::roles() as $rol) {
            $this->assertFalse(
                $this->rol($rol)->hasPermissionTo('administrar_marca'),
                "El rol «{$rol}» puede cambiarle la marca a la empresa.",
            );
            $this->assertFalse(
                $this->rol($rol)->hasPermissionTo('Create:User'),
                "El rol «{$rol}» puede crear usuarios.",
            );
        }
    }

    // ── El comando para los que ya existían ─────────────────────────────────

    public function test_el_comando_llena_los_roles_vacios(): void
    {
        Tenancy::comoEmpresa($this->empresa, fn () => $this->rol('vendedor')->syncPermissions([]));

        $this->artisan('lotea:permisos-por-rol', ['empresa' => $this->empresa->slug])
            ->assertSuccessful();

        Tenancy::usar($this->empresa);

        $this->assertGreaterThan(0, $this->rol('vendedor')->permissions()->count());
    }

    /** Nadie quiere que un comando le deshaga lo que estuvo ajustando. */
    public function test_el_comando_respeta_lo_que_el_cliente_configuro(): void
    {
        Tenancy::comoEmpresa($this->empresa, fn () => $this->rol('vendedor')->syncPermissions(['ViewAny:Unidad']));

        $this->artisan('lotea:permisos-por-rol', ['empresa' => $this->empresa->slug])->assertSuccessful();

        Tenancy::usar($this->empresa);

        $this->assertSame(1, $this->rol('vendedor')->permissions()->count());
    }

    public function test_con_forzar_si_lo_reemplaza(): void
    {
        Tenancy::comoEmpresa($this->empresa, fn () => $this->rol('vendedor')->syncPermissions(['ViewAny:Unidad']));

        $this->artisan('lotea:permisos-por-rol', [
            'empresa' => $this->empresa->slug,
            '--forzar' => true,
        ])->assertSuccessful();

        Tenancy::usar($this->empresa);

        $this->assertGreaterThan(1, $this->rol('vendedor')->permissions()->count());
    }

    /** Los roles de un concesionario no se ven desde otro. */
    public function test_los_permisos_son_de_cada_empresa(): void
    {
        $otra = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Norte']);

        Tenancy::comoEmpresa($otra, fn () => $this->rol('vendedor')->syncPermissions([]));

        Tenancy::usar($this->empresa);

        $this->assertGreaterThan(
            0,
            $this->rol('vendedor')->permissions()->count(),
            'Vaciar el rol de un concesionario afectó al de otro.',
        );
    }
}
