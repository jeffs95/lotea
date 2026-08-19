<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Traduce los permisos técnicos de Shield a algo que se pueda leer por
 * teléfono con el dueño de un concesionario.
 *
 * Shield los nombra ViewAny:Unidad, Create:Venta y así; nadie va a entender
 * eso en una llamada de soporte.
 */
class CatalogoDePermisos
{
    /** Cómo se llama cada recurso en cristiano. */
    public const MODULOS = [
        'Unidad' => 'Unidades',
        'Venta' => 'Ventas',
        'Cliente' => 'Clientes',
        'Lead' => 'Prospectos',
        'GastoCompartido' => 'Gastos compartidos',
        'Proveedor' => 'Proveedores',
        'Sucursal' => 'Sucursales',
        'CategoriaCosto' => 'Categorías de costo',
        'Marca' => 'Marcas y líneas',
        'User' => 'Usuarios',
        'Role' => 'Roles',
        'TableroUnidades' => 'Tablero del patio',
        'RentabilidadUnidad' => 'Rentabilidad',
        // Shield también permisiona los widgets del escritorio.
        'CapitalEnPatio' => 'Escritorio: capital en patio',
        'UnidadesEstancadas' => 'Escritorio: unidades estancadas',
        'Plan' => 'Planes (Lotea)',
        'Cobro' => 'Cobros (Lotea)',
        'Empresa' => 'Concesionarios (Lotea)',
    ];

    /** Y cada acción. */
    public const ACCIONES = [
        'ViewAny' => 'Ver el listado',
        'View' => 'Ver el detalle',
        'Create' => 'Crear',
        'Update' => 'Editar',
        'Delete' => 'Borrar',
        'DeleteAny' => 'Borrar en lote',
        'Restore' => 'Restaurar',
        'RestoreAny' => 'Restaurar en lote',
        'ForceDelete' => 'Borrar definitivamente',
        'ForceDeleteAny' => 'Borrar definitivamente en lote',
        'Replicate' => 'Duplicar',
        'Reorder' => 'Reordenar',
    ];

    /** Los que no salen de un recurso y hay que explicar aparte. */
    public const PROPIOS = [
        'ver_costos_unidad' => ['modulo' => 'Dinero', 'accion' => 'Ver costos y márgenes'],
        'ver_precio_minimo' => ['modulo' => 'Dinero', 'accion' => 'Ver el precio mínimo autorizado'],
    ];

    /** El orden en que conviene leerlos en una llamada. */
    public const ORDEN = [
        'Unidades', 'Ventas', 'Clientes', 'Prospectos', 'Dinero', 'Gastos compartidos',
        'Tablero del patio', 'Rentabilidad', 'Proveedores', 'Sucursales',
        'Categorías de costo', 'Marcas y líneas', 'Usuarios', 'Roles',
        'Escritorio: capital en patio', 'Escritorio: unidades estancadas',
    ];

    /**
     * @param  Collection<int, string>  $concedidos  nombres de permisos que el usuario tiene
     * @return Collection<string, array> módulo => lista de acciones con su estado
     */
    public static function agrupar(Collection $todos, Collection $concedidos): Collection
    {
        $tiene = $concedidos->flip();

        return $todos
            ->map(fn (string $permiso) => [
                ...self::describir($permiso),
                'permiso' => $permiso,
                'concedido' => $tiene->has($permiso),
            ])
            ->groupBy('modulo')
            ->sortBy(fn (Collection $acciones, string $modulo) => self::posicion($modulo))
            ->map(fn (Collection $acciones) => $acciones
                ->sortBy(fn (array $a) => array_search($a['clave'], array_keys(self::ACCIONES), true))
                ->values()
                ->all());
    }

    /** @return array{modulo: string, accion: string, clave: string} */
    public static function describir(string $permiso): array
    {
        if (isset(self::PROPIOS[$permiso])) {
            return [...self::PROPIOS[$permiso], 'clave' => $permiso];
        }

        if (! str_contains($permiso, ':')) {
            return ['modulo' => 'Otros', 'accion' => Str::headline($permiso), 'clave' => $permiso];
        }

        [$accion, $recurso] = explode(':', $permiso, 2);

        return [
            'modulo' => self::MODULOS[$recurso] ?? Str::headline($recurso),
            'accion' => self::ACCIONES[$accion] ?? Str::headline($accion),
            'clave' => $accion,
        ];
    }

    protected static function posicion(string $modulo): int
    {
        $indice = array_search($modulo, self::ORDEN, true);

        return $indice === false ? 999 : $indice;
    }
}
