<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
use App\Filament\Resources\Ventas\Pages\CreateVenta;
use App\Filament\Resources\Ventas\VentaResource;
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
 * Cada vendedor ve su propio trabajo.
 *
 * Buena parte de los vendedores del patio no son empleados: tienen sus propias
 * actividades y además publican los carros en redes, cobrando por unidad
 * vendida. Lo que gana cada uno se negocia aparte y no tiene por qué coincidir.
 *
 * Que uno abra la pantalla de ventas y lea lo que cobró el otro no es una fuga
 * de datos de la empresa: es un problema entre personas, y de los que no se
 * arreglan después.
 */
class VentasDeCadaVendedorTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected User $ana;

    protected User $bruno;

    protected Venta $deAna;

    protected Venta $deBruno;

    protected function setUp(): void
    {
        parent::setUp();

        // Los roles se reparten contra los permisos que existan: sin esto
        // nacen vacíos y el caso pasaría por no poder nadie hacer nada.
        (new PermisosDeShieldSeeder)->run();
        (new PermisosPropiosSeeder)->run();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        Tenancy::usar($this->empresa);

        $this->ana = $this->conRol('Ana', 'vendedor');
        $this->bruno = $this->conRol('Bruno', 'vendedor');

        $this->deAna = $this->ventaDe($this->ana, '1200.00');
        $this->deBruno = $this->ventaDe($this->bruno, '3500.00');
    }

    protected function conRol(string $nombre, string $rol): User
    {
        $usuario = User::factory()->create(['name' => $nombre]);
        $usuario->empresas()->attach($this->empresa->getKey());
        $usuario->assignRole($rol);

        return $usuario->fresh();
    }

    protected function ventaDe(User $vendedor, string $comision): Venta
    {
        return Venta::create([
            'empresa_id' => $this->empresa->getKey(),
            'unidad_id' => Unidad::factory()->create()->getKey(),
            'cliente_id' => Cliente::factory()->create()->getKey(),
            'vendedor_id' => $vendedor->getKey(),
            'numero' => 'V-'.$vendedor->getKey(),
            'estado' => 'cerrada',
            'fecha' => today(),
            'precio_venta' => '100000.00',
            'precio_final' => '100000.00',
            'comision_base' => 'fijo',
            'comision_acordada' => $comision,
            'comision_monto' => $comision,
        ]);
    }

    /** @return array<int, int> */
    protected function loQueVe(User $usuario): array
    {
        return $this->actingAs($usuario)->app->call(
            fn () => VentaResource::getEloquentQuery()->pluck('id')->all()
        );
    }

    public function test_el_vendedor_solo_ve_sus_ventas(): void
    {
        $this->assertSame([$this->deAna->getKey()], $this->loQueVe($this->ana));
        $this->assertSame([$this->deBruno->getKey()], $this->loQueVe($this->bruno));
    }

    /**
     * Filtrar el listado cambia lo que se ve, no lo que se puede pedir. Quien
     * teclea el id de otra venta en la barra de direcciones llega igual.
     */
    public function test_el_vendedor_no_puede_abrir_la_venta_de_otro(): void
    {
        $this->assertTrue($this->ana->can('view', $this->deAna));
        $this->assertFalse($this->ana->can('view', $this->deBruno));
    }

    public function test_el_vendedor_no_puede_editar_la_venta_de_otro(): void
    {
        $this->assertTrue($this->ana->can('update', $this->deAna));
        $this->assertFalse($this->ana->can('update', $this->deBruno));
    }

    /** Quien dirige el patio necesita el cuadro completo para pagar. */
    public function test_quien_lleva_el_negocio_las_ve_todas(): void
    {
        $gerente = $this->conRol('Gabriela', 'gerente_sucursal');

        $this->assertEqualsCanonicalizing(
            [$this->deAna->getKey(), $this->deBruno->getKey()],
            $this->loQueVe($gerente),
        );

        $this->assertTrue($gerente->can('view', $this->deAna));
        $this->assertTrue($gerente->can('view', $this->deBruno));
    }

    /** El vendedor no trae ese permiso de fábrica: si lo trajera, sobra todo esto. */
    public function test_el_permiso_no_viene_en_el_rol_de_vendedor(): void
    {
        $this->assertFalse($this->ana->can('ver_ventas_ajenas'));
        $this->assertTrue($this->conRol('Gabriela', 'gerente_sucursal')->can('ver_ventas_ajenas'));
    }

    /**
     * El campo del formulario viene fijo, pero eso vive en el navegador.
     *
     * Si alguien lo manipula y manda la venta a nombre de otro, se le estaría
     * cargando —o quitando— una comisión a un compañero. El servidor la deja
     * a nombre de quien la escribió.
     */
    public function test_el_vendedor_no_puede_cargar_una_venta_a_nombre_de_otro(): void
    {
        $unidad = Unidad::factory()->publicada()->create([
            'estado' => EstadoUnidad::Publicada,
            'precio_lista' => 148000,
        ]);

        $this->actingAs($this->ana);
        Filament::setTenant($this->empresa);

        Livewire::test(CreateVenta::class)
            ->fillForm([
                'unidad_id' => $unidad->getKey(),
                'cliente_id' => Cliente::factory()->create()->getKey(),
                'vendedor_id' => $this->bruno->getKey(),
                'estado' => 'cerrada',
                'fecha' => today(),
                'precio_venta' => 145000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(
            $this->ana->getKey(),
            Venta::where('unidad_id', $unidad->getKey())->value('vendedor_id'),
        );
    }

    /** A quien sí lleva el negocio no se le toca lo que escribió. */
    public function test_a_quien_puede_se_le_respeta_el_vendedor_que_eligio(): void
    {
        $unidad = Unidad::factory()->publicada()->create([
            'estado' => EstadoUnidad::Publicada,
            'precio_lista' => 148000,
        ]);

        $this->actingAs($this->conRol('Gabriela', 'gerente_sucursal'));
        Filament::setTenant($this->empresa);

        Livewire::test(CreateVenta::class)
            ->fillForm([
                'unidad_id' => $unidad->getKey(),
                'cliente_id' => Cliente::factory()->create()->getKey(),
                'vendedor_id' => $this->bruno->getKey(),
                'estado' => 'cerrada',
                'fecha' => today(),
                'precio_venta' => 145000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(
            $this->bruno->getKey(),
            Venta::where('unidad_id', $unidad->getKey())->value('vendedor_id'),
        );
    }

    /**
     * Una consulta sin sesión es un error de programación. Entre fallar y
     * enseñarlo todo, falla.
     */
    public function test_sin_usuario_no_se_devuelve_ninguna(): void
    {
        $this->assertSame(0, Venta::query()->visiblesPara(null)->count());
    }
}
