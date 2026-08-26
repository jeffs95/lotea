<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Plan;
use App\Models\Sucursal;
use App\Models\Unidad;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\ClienteInicialSeeder;
use Database\Seeders\PermisosDeShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El paso a producción de un concesionario que ya se armó en otro lado.
 *
 * Los datos no van escritos en el código: el nombre de un cliente, sus correos,
 * sus VIN y sus precios son suyos, y este repositorio es público. Estuvieron
 * dentro un tiempo, así que además del comportamiento se prueba que no vuelvan.
 */
class ClienteInicialSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new PermisosDeShieldSeeder)->run();
        Plan::firstOrCreate(['slug' => 'pro'], ['nombre' => 'Pro', 'precio_mensual' => 0]);
    }

    protected function sembrar(array $datos): void
    {
        putenv('SEMILLA_CLIENTE='.json_encode($datos));
        $_ENV['SEMILLA_CLIENTE'] = json_encode($datos);

        (new ClienteInicialSeeder)->run();

        putenv('SEMILLA_CLIENTE');
        unset($_ENV['SEMILLA_CLIENTE']);
    }

    protected function datosDePrueba(): array
    {
        return [
            'empresa' => [
                'nombre' => 'Autos de Prueba',
                'slug' => 'autos-de-prueba',
                'email' => 'ventas@prueba.gt',
                'plan' => 'pro',
            ],
            'sucursal' => [
                'codigo' => 'PRIN',
                'nombre' => 'Sala central',
                'direccion' => 'Zona 1',
            ],
            'usuarios' => [
                ['email' => 'dueno@prueba.gt', 'nombre' => 'Dueño', 'rol' => 'dueno', 'clave_env' => 'PRUEBA_CLAVE'],
            ],
            'caja' => ['nombre' => 'Caja chica', 'moneda' => 'GTQ', 'saldo_inicial' => 500],
            'unidades' => [[
                'stock_no' => '0001',
                'vin' => '1HGCM82633A004352',
                'marca' => 'Toyota',
                'linea' => 'Corolla',
                'anio' => 2019,
                'tipo_vehiculo' => 'automovil',
                'estado' => 'lista',
                'precio_lista' => 85000,
                'slug' => 'toyota-corolla-2019-0001',
            ]],
        ];
    }

    public function test_siembra_el_concesionario_con_lo_suyo(): void
    {
        $this->sembrar($this->datosDePrueba());

        $empresa = Empresa::withoutGlobalScopes()->firstWhere('slug', 'autos-de-prueba');
        $this->assertNotNull($empresa, 'No se creó la empresa.');

        Tenancy::comoEmpresa($empresa, function () {
            $this->assertNotNull(Sucursal::where('codigo', 'PRIN')->first());
            $this->assertSame(1, Unidad::count());
            $this->assertSame('Toyota Corolla 2019', Unidad::first()->descripcion);
        });

        $usuario = User::firstWhere('email', 'dueno@prueba.gt');
        $this->assertNotNull($usuario);
        $this->assertTrue($usuario->empresas()->whereKey($empresa->getKey())->exists());
    }

    /** Se corre más de una vez sin duplicar: si algo falla se reintenta. */
    public function test_correrlo_dos_veces_no_duplica_nada(): void
    {
        $this->sembrar($this->datosDePrueba());
        $this->sembrar($this->datosDePrueba());

        $this->assertSame(1, Empresa::withoutGlobalScopes()->where('slug', 'autos-de-prueba')->count());
        $this->assertSame(1, User::where('email', 'dueno@prueba.gt')->count());

        $empresa = Empresa::withoutGlobalScopes()->firstWhere('slug', 'autos-de-prueba');
        Tenancy::comoEmpresa($empresa, fn () => $this->assertSame(1, Unidad::count()));
    }

    /** Sin datos no revienta ni siembra a medias: avisa y se va. */
    public function test_sin_datos_no_hace_nada(): void
    {
        // En la máquina de quien desarrolla suele haber un cliente.json de
        // verdad; se aparta un momento para probar el caso vacío.
        $archivo = database_path('seeders/datos/cliente.json');
        $guardado = is_file($archivo) ? file_get_contents($archivo) : null;

        if ($guardado !== null) {
            unlink($archivo);
        }

        try {
            (new ClienteInicialSeeder)->run();

            $this->assertSame(0, Empresa::withoutGlobalScopes()->count());
        } finally {
            if ($guardado !== null) {
                file_put_contents($archivo, $guardado);
            }
        }
    }

    /**
     * El guardián del repositorio.
     *
     * Los datos del cliente estuvieron escritos dentro del seeder y este
     * repositorio es público. Que no vuelvan a entrar sin que algo se queje.
     */
    public function test_ningun_seeder_lleva_datos_de_un_cliente_de_verdad(): void
    {
        $sospechosos = [];

        // Lo que git tiene rastreado, que es lo que de verdad se publica: un
        // cliente.json en la máquina de alguien está ignorado y no cuenta.
        exec('git -C '.escapeshellarg(base_path()).' ls-files database 2>/dev/null', $rastreados);

        $this->assertNotEmpty($rastreados, 'No se pudo preguntar a git qué archivos están en el repositorio.');

        foreach ($rastreados as $relativo) {
            if (str_ends_with($relativo, 'cliente.ejemplo.json') || ! is_file(base_path($relativo))) {
                continue;
            }

            $archivo = new \SplFileInfo(base_path($relativo));
            $texto = (string) file_get_contents($archivo->getPathname());

            // Correos de personas: los de ejemplo viven en dominios de ejemplo.
            preg_match_all('/[\w.+-]+@[\w-]+\.[\w.]+/', $texto, $correos);

            foreach ($correos[0] as $correo) {
                if (! preg_match('/@(ejemplo|prueba|example|test|lotea)\./i', $correo)) {
                    $sospechosos[] = $relativo.' → '.$correo;
                }
            }
        }

        $this->assertSame(
            [],
            $sospechosos,
            "Hay datos de una persona de verdad en un seeder, y el repositorio es público:\n"
            .implode("\n", $sospechosos),
        );
    }

    /** El ejemplo tiene que servir de guía: si se queda viejo, no sirve. */
    public function test_el_ejemplo_se_puede_sembrar_tal_cual(): void
    {
        $ejemplo = json_decode(
            (string) file_get_contents(database_path('seeders/datos/cliente.ejemplo.json')),
            true,
        );

        $this->assertIsArray($ejemplo, 'El ejemplo no es un JSON válido.');

        $this->sembrar($ejemplo);

        $this->assertNotNull(
            Empresa::withoutGlobalScopes()->firstWhere('slug', $ejemplo['empresa']['slug']),
            'El archivo de ejemplo ya no coincide con lo que el seeder espera.',
        );
    }
}
