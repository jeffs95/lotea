<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\Role;
use App\Support\PermisosPorRol;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Le pone a cada rol el juego de permisos con el que debería haber nacido.
 *
 * Los concesionarios dados de alta antes tienen sus roles vacíos: hay que
 * pasarles esto una vez. Los nuevos ya salen configurados solos.
 *
 * Por defecto no toca lo que el cliente haya armado a mano —solo llena los
 * roles que están vacíos— porque nadie quiere que un comando le deshaga los
 * permisos que estuvo ajustando.
 */
class AplicarPermisosPorRol extends Command
{
    protected $signature = 'lotea:permisos-por-rol
        {empresa? : Slug del concesionario. Sin esto, todos}
        {--forzar : Reemplaza también los roles que ya tienen permisos}';

    protected $description = 'Da a los roles de cada concesionario los permisos con los que deberían nacer';

    public function handle(): int
    {
        $empresas = Empresa::query()
            ->when($this->argument('empresa'), fn ($q, $slug) => $q->where('slug', $slug))
            ->get();

        if ($empresas->isEmpty()) {
            $this->warn('No hay concesionarios que procesar.');

            return self::SUCCESS;
        }

        $forzar = (bool) $this->option('forzar');

        foreach ($empresas as $empresa) {
            $this->line("<fg=cyan>{$empresa->getFilamentName()}</>");

            Tenancy::comoEmpresa($empresa, fn () => $this->aplicar($forzar));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return self::SUCCESS;
    }

    protected function aplicar(bool $forzar): void
    {
        // El dueño manda dentro de su empresa, y los permisos crecen con cada
        // módulo nuevo: se le vuelven a dar todos.
        Role::findOrCreate('dueno', 'web')->syncPermissions(Permission::all());
        $this->line('  <fg=green>dueno</> todos los permisos');

        foreach (PermisosPorRol::roles() as $nombre) {
            $rol = Role::findOrCreate($nombre, 'web');
            $tenia = $rol->permissions()->count();

            if ($tenia > 0 && ! $forzar) {
                $this->line("  <fg=gray>{$nombre}</> ya tenía {$tenia}; se deja como está");

                continue;
            }

            $permisos = PermisosPorRol::para($nombre);
            $rol->syncPermissions($permisos);

            $this->line("  <fg=green>{$nombre}</> ".count($permisos).' permisos');
        }
    }
}
