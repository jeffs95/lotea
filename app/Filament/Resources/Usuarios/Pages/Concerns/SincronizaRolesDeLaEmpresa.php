<?php

namespace App\Filament\Resources\Usuarios\Pages\Concerns;

use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Guarda los roles del usuario pasando por spatie y no por Eloquent.
 *
 * Los roles son por empresa —lo que spatie llama «teams»— y el vínculo lleva
 * su empresa_id. Dejar que Filament escriba la tabla pivote con
 * ->relationship('roles') se salta assignRole() y esa columna se queda vacía:
 * la base lo rechaza. Y si no lo rechazara sería peor, porque el rol quedaría
 * suelto y el usuario aparecería con él dentro de cualquier otro concesionario.
 */
trait SincronizaRolesDeLaEmpresa
{
    /** @var array<int, int|string> */
    protected array $rolesElegidos = [];

    /**
     * El campo se saca del guardado normal y se aparta para después.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    protected function apartarRoles(array $datos): array
    {
        $this->rolesElegidos = array_filter((array) ($datos['roles'] ?? []));

        unset($datos['roles']);

        return $datos;
    }

    /** Ya con el usuario guardado, spatie los asigna con la empresa activa. */
    protected function guardarRoles(): void
    {
        $nombres = Role::whereKey($this->rolesElegidos)->pluck('name')->all();

        $this->record->syncRoles($nombres);

        // El caché de permisos recuerda lo que el usuario podía hacer hasta
        // hace un segundo; sin esto, el panel seguiría mostrándole lo de antes.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Lo que el formulario tiene que mostrar al abrir la ficha.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    protected function cargarRoles(array $datos): array
    {
        $datos['roles'] = $this->record->roles->pluck('id')->all();

        return $datos;
    }
}
