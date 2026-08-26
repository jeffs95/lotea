<?php

namespace Database\Seeders;

use App\Actions\CrearEmpresa;
use App\Actions\GenerarVariantesDeMarca;
use App\Enums\EstadoUnidad;
use App\Models\Caja;
use App\Models\Empresa;
use App\Models\Linea;
use App\Models\Marca;
use App\Models\Plan;
use App\Models\Sucursal;
use App\Models\Unidad;
use App\Models\User;
use App\Support\AlmacenDeArchivos;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Pasa a producción un concesionario que ya se armó en otro lado.
 *
 * Sirve para el primer cliente y para cualquiera que venga después: los datos
 * no están escritos aquí, se leen de un archivo que no entra al repositorio.
 * Ese es el punto — el nombre de un cliente, sus correos, sus VIN y sus precios
 * son suyos, y esto es código abierto.
 *
 *     php artisan db:seed --class=ClienteInicialSeeder
 *
 * De dónde salen los datos, en este orden:
 *
 *  1. La variable de entorno SEMILLA_CLIENTE, con el JSON completo. Es la vía
 *     para Heroku, donde no se puede dejar un archivo suelto.
 *  2. database/seeders/datos/cliente.json, para correrlo desde una máquina.
 *
 * La forma exacta está en datos/cliente.ejemplo.json, que sí va al repositorio
 * porque no tiene datos de nadie.
 *
 * Es idempotente: correrlo dos veces no duplica nada. Los archivos —logo, fotos,
 * documentos— se traen del disco donde ya están, para que medialibrary los
 * registre con la ruta que les toque en la base nueva.
 *
 * Las contraseñas nunca van en el archivo: cada usuario dice de qué variable de
 * entorno sale la suya. Sin ella queda una temporal y se avisa en pantalla.
 */
class ClienteInicialSeeder extends Seeder
{
    protected const CLAVE_TEMPORAL = 'cambiar-esta-clave';

    protected const ARCHIVO = 'datos/cliente.json';

    /** @var array<string, mixed> */
    protected array $datos = [];

    /** @var array<int, string> */
    protected array $clavesSinDefinir = [];

    public function run(): void
    {
        $datos = $this->leerLosDatos();

        if ($datos === null) {
            $this->command?->warn(
                'No hay datos que sembrar. Poné el JSON en SEMILLA_CLIENTE o en '
                .'database/seeders/'.self::ARCHIVO.'; la forma está en datos/cliente.ejemplo.json.'
            );

            return;
        }

        $this->datos = $datos;

        $empresa = $this->empresa();

        Tenancy::comoEmpresa($empresa, function () use ($empresa) {
            $this->sucursal();
            $this->usuarios($empresa);
            $this->caja();
            $this->unidades();
        });

        $this->marca($empresa);

        $this->command?->newLine();
        $this->command?->info($empresa->getFilamentName().' quedó lista.');
        $this->avisarDeLasClaves();
    }

    /** @return array<string, mixed>|null */
    protected function leerLosDatos(): ?array
    {
        $crudo = (string) env('SEMILLA_CLIENTE', '');

        if (blank($crudo)) {
            $archivo = database_path('seeders/'.self::ARCHIVO);

            if (! is_file($archivo)) {
                return null;
            }

            $crudo = (string) file_get_contents($archivo);
        }

        $datos = json_decode($crudo, true);

        if (! is_array($datos) || ! isset($datos['empresa'])) {
            $this->command?->error('Los datos no son un JSON válido con una clave «empresa».');

            return null;
        }

        return $datos;
    }

    protected function empresa(): Empresa
    {
        $datos = $this->datos['empresa'];

        // El plan viene por su slug: los id cambian entre instalaciones.
        $plan = $datos['plan'] ?? null;
        unset($datos['plan']);
        $datos['plan_id'] = $plan ? Plan::firstWhere('slug', $plan)?->id : null;

        $empresa = Empresa::withoutGlobalScopes()->firstWhere('slug', $datos['slug']);

        if ($empresa) {
            $empresa->update($datos);
            $this->command?->line('  <fg=gray>la empresa ya existía; se actualizaron sus datos</>');

            return $empresa;
        }

        // El alta trae de regalo la sucursal principal, los diez roles con sus
        // permisos y las categorías de costo.
        $empresa = (new CrearEmpresa)->ejecutar($datos, $this->datos['sucursal']['nombre'] ?? null);

        $this->command?->line('  <fg=green>empresa creada</> con sus roles y catálogos');

        return $empresa;
    }

    protected function sucursal(): void
    {
        $datos = $this->datos['sucursal'] ?? null;

        if (blank($datos)) {
            return;
        }

        $codigo = $datos['codigo'] ?? 'PRIN';
        unset($datos['codigo']);

        Sucursal::updateOrCreate(['codigo' => $codigo], [
            ...$datos,
            'es_principal' => true,
            'activa' => true,
            'mostrar_en_portal' => true,
        ]);

        $this->command?->line('  <fg=green>sucursal</> '.($datos['nombre'] ?? $codigo));
    }

    protected function usuarios(Empresa $empresa): void
    {
        foreach ($this->datos['usuarios'] ?? [] as $persona) {
            $variable = $persona['clave_env'] ?? null;
            $clave = $variable ? env($variable) : null;

            if ($variable && blank($clave)) {
                $this->clavesSinDefinir[] = $variable;
            }

            $usuario = User::firstOrCreate(
                ['email' => $persona['email']],
                [
                    'name' => $persona['nombre'],
                    'password' => $clave ?: self::CLAVE_TEMPORAL,
                    'activo' => true,
                ],
            );

            $usuario->empresas()->syncWithoutDetaching([$empresa->id]);
            $usuario->syncRoles([$persona['rol']]);

            $this->command?->line("  <fg=green>usuario</> {$persona['email']} · {$persona['rol']}");
        }
    }

    protected function caja(): void
    {
        $datos = $this->datos['caja'] ?? null;

        if (blank($datos)) {
            return;
        }

        Caja::updateOrCreate(['nombre' => $datos['nombre']], [
            'sucursal_id' => Sucursal::where('codigo', $this->datos['sucursal']['codigo'] ?? 'PRIN')->value('id'),
            'moneda' => $datos['moneda'] ?? 'GTQ',
            'saldo_inicial' => $datos['saldo_inicial'] ?? 0,
            'activa' => true,
        ]);

        $this->command?->line('  <fg=green>caja</> '.$datos['nombre']);
    }

    protected function unidades(): void
    {
        foreach ($this->datos['unidades'] ?? [] as $ficha) {
            $archivos = $ficha['archivos'] ?? [];
            unset($ficha['archivos']);

            $ficha['estado'] = EstadoUnidad::from($ficha['estado']);
            $ficha['sucursal_id'] = Sucursal::where('codigo', $this->datos['sucursal']['codigo'] ?? 'PRIN')->value('id');
            $ficha['marca_id'] = $this->marcaDe($ficha['marca']);
            $ficha['linea_id'] = $this->lineaDe($ficha['marca_id'], $ficha['linea']);
            unset($ficha['marca'], $ficha['linea']);

            $unidad = Unidad::withTrashed()->firstWhere('vin', $ficha['vin']);

            if ($unidad) {
                $unidad->update($ficha);
                $this->command?->line("  <fg=gray>unidad {$ficha['stock_no']} ya existía; datos actualizados</>");
            } else {
                $unidad = Unidad::create($ficha);
                $this->command?->line("  <fg=green>unidad</> {$ficha['stock_no']} · {$unidad->descripcion}");
            }

            $this->archivos($unidad, $archivos);

            // El sistema no publica un carro sin fotos, y al crearlo todavía no
            // las tenía: se marca ahora, que ya están arriba.
            //
            // El refresh no es opcional: medialibrary recuerda en la instancia
            // las fotos que había al cargarla, y sobre esa copia vieja el
            // guardián no ve ninguna y vuelve a apagar «publicado».
            $unidad->refresh();

            if (($ficha['publicado'] ?? false) && ! $unidad->publicado) {
                $unidad->update(['publicado' => true]);

                $this->command?->line('    <fg=green>publicada</> en el portal');
            }
        }
    }

    /**
     * @param  array<string, array<int, string>>  $porColeccion
     */
    protected function archivos(Unidad $unidad, array $porColeccion): void
    {
        foreach ($porColeccion as $coleccion => $rutas) {
            // Si ya tiene, no se vuelven a subir: el seeder puede correrse otra vez.
            if ($unidad->getMedia($coleccion)->isNotEmpty()) {
                continue;
            }

            foreach ($rutas as $ruta) {
                try {
                    $unidad->addMediaFromDisk($ruta, AlmacenDeArchivos::nombreDelDisco())
                        ->preservingOriginal()
                        ->toMediaCollection($coleccion);
                } catch (Throwable $e) {
                    $this->command?->warn("    no se pudo traer «{$ruta}»: ".$e->getMessage());
                }
            }

            $this->command?->line("    <fg=green>{$coleccion}</> ".count($rutas).' archivo(s)');
        }
    }

    /** Las marcas del catálogo son de Lotea; si falta, se crea para el cliente. */
    protected function marcaDe(string $nombre): int
    {
        $marca = Marca::where('nombre', $nombre)->first();

        return $marca?->id ?? Marca::create([
            'nombre' => $nombre,
            'slug' => str($nombre)->slug()->value(),
        ])->id;
    }

    protected function lineaDe(int $marcaId, string $nombre): int
    {
        $linea = Linea::where('marca_id', $marcaId)->where('nombre', $nombre)->first();

        return $linea?->id ?? Linea::create([
            'marca_id' => $marcaId,
            'nombre' => $nombre,
            'slug' => str($nombre)->slug()->value(),
        ])->id;
    }

    /**
     * El logo y sus versiones.
     *
     * El archivo original ya está en el disco; de él se derivan las demás con la
     * misma acción que corre cuando el cliente sube su logo desde el panel.
     */
    protected function marca(Empresa $empresa): void
    {
        $original = $this->datos['logo'] ?? null;

        if (blank($original) || ! AlmacenDeArchivos::disco()->exists($original)) {
            $this->command?->warn('  el logo no está en el disco; la marca queda sin logo');

            return;
        }

        $empresa->update(['logo_path' => $original]);

        $cambios = app(GenerarVariantesDeMarca::class)->ejecutar($empresa, forzar: true);

        $this->command?->line('  <fg=green>marca</> logo y '.count($cambios).' versiones derivadas');
    }

    protected function avisarDeLasClaves(): void
    {
        $sinDefinir = array_unique($this->clavesSinDefinir);

        if ($sinDefinir === []) {
            return;
        }

        $this->command?->warn(
            'Los usuarios quedaron con la clave temporal «'.self::CLAVE_TEMPORAL.'». '
            .'Definí '.implode(' y ', $sinDefinir).' antes de sembrar, o cambiálas desde el panel.'
        );
    }
}
