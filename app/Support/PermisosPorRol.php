<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;

/**
 * Con qué puede empezar a trabajar cada rol el primer día.
 *
 * Antes los diez roles nacían vacíos y el dueño tenía que armarlos a mano antes
 * de que nadie pudiera hacer nada: creaba un vendedor, el vendedor entraba y no
 * veía ni el inventario. Esto es el punto de partida razonable; el dueño puede
 * ajustar cualquiera desde la pantalla de Roles.
 *
 * Las dos reglas que no se tocan sin pensarlo dos veces:
 *
 * - **Ver costos y márgenes** solo lo tiene quien decide plata: el dueño, el
 *   gerente, quien compra, quien coordina la importación, el taller —que carga
 *   costo— y el contador. Si el vendedor ve lo que costó el carro, el patrón
 *   pierde su margen en la negociación.
 * - **Ver el precio mínimo autorizado** es todavía más estrecho: quien lo sabe
 *   puede regalar el piso completo.
 */
class PermisosPorRol
{
    /** Lo normal de una pantalla donde solo se consulta. */
    protected const LECTURA = ['ViewAny', 'View'];

    /** Consultar y dar de alta, sin poder borrar. */
    protected const ALTA = ['ViewAny', 'View', 'Create', 'Update'];

    /** El juego completo de una pantalla que se administra de verdad. */
    protected const COMPLETO = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Restore', 'RestoreAny'];

    /**
     * Qué módulos toca cada rol y con qué alcance.
     *
     * @return array<string, array{modulos: array<string, array<int, string>>, propios: array<int, string>}>
     */
    public static function mapa(): array
    {
        return [
            // El patio entero, salvo la marca y los usuarios, que son del dueño.
            'gerente_sucursal' => [
                'modulos' => [
                    'Unidad' => self::COMPLETO,
                    'Venta' => self::COMPLETO,
                    'Cliente' => self::COMPLETO,
                    'Lead' => self::COMPLETO,
                    'Caja' => self::COMPLETO,
                    'GastoCompartido' => self::COMPLETO,
                    'OrdenTrabajo' => self::COMPLETO,
                    'PlanPago' => self::ALTA,
                    'Empleado' => self::ALTA,
                    'Proveedor' => self::ALTA,
                    'Marca' => self::ALTA,
                    'CategoriaCosto' => self::LECTURA,
                    'Sucursal' => self::LECTURA,
                    'TableroUnidades' => self::LECTURA,
                    'Levantamiento' => self::LECTURA,
                    'CapitalEnPatio' => self::LECTURA,
                    'UnidadesEstancadas' => self::LECTURA,
                ],
                'propios' => ['ver_costos_unidad', 'ver_precio_minimo'],
            ],

            // Quien va a las subastas: registra lo que compra y con qué costo.
            'comprador' => [
                'modulos' => [
                    'Unidad' => self::ALTA,
                    'GastoCompartido' => self::ALTA,
                    'Proveedor' => self::ALTA,
                    'Marca' => self::ALTA,
                    'TableroUnidades' => self::LECTURA,
                    'Levantamiento' => self::LECTURA,
                    'CapitalEnPatio' => self::LECTURA,
                    'UnidadesEstancadas' => self::LECTURA,
                ],
                'propios' => ['ver_costos_unidad'],
            ],

            // Mueve el carro de la subasta al patio y reparte los fletes.
            'coordinador_importaciones' => [
                'modulos' => [
                    'Unidad' => ['ViewAny', 'View', 'Update'],
                    'GastoCompartido' => self::COMPLETO,
                    'Proveedor' => self::ALTA,
                    'TableroUnidades' => self::LECTURA,
                    'CapitalEnPatio' => self::LECTURA,
                    'UnidadesEstancadas' => self::LECTURA,
                ],
                'propios' => ['ver_costos_unidad'],
            ],

            // El taller carga costo a la unidad, así que necesita verlo.
            'jefe_taller' => [
                'modulos' => [
                    'OrdenTrabajo' => self::COMPLETO,
                    'Unidad' => ['ViewAny', 'View', 'Update'],
                    'Empleado' => self::LECTURA,
                    'Proveedor' => self::ALTA,
                    'TableroUnidades' => self::LECTURA,
                ],
                'propios' => ['ver_costos_unidad'],
            ],

            // Solo su trabajo: qué carro le toca y qué le hizo.
            'mecanico' => [
                'modulos' => [
                    'OrdenTrabajo' => ['ViewAny', 'View', 'Update'],
                    'Unidad' => self::LECTURA,
                ],
                'propios' => [],
            ],

            // Vende y atiende prospectos. Nunca ve lo que costó el carro.
            'vendedor' => [
                'modulos' => [
                    'Unidad' => self::LECTURA,
                    'Venta' => self::ALTA,
                    'Cliente' => self::ALTA,
                    'Lead' => ['ViewAny', 'View', 'Update'],
                    'Levantamiento' => self::LECTURA,
                    'TableroUnidades' => self::LECTURA,
                ],
                'propios' => [],
            ],

            // Recibe el dinero y cobra las cuotas del crédito propio.
            'cajero' => [
                'modulos' => [
                    'Caja' => self::COMPLETO,
                    'Venta' => self::LECTURA,
                    'Cliente' => self::LECTURA,
                    'PlanPago' => ['ViewAny', 'View', 'Update'],
                ],
                'propios' => [],
            ],

            // Mira todo el dinero, pero no opera: no vende ni mueve caja.
            'contador' => [
                'modulos' => [
                    'Unidad' => self::LECTURA,
                    'Venta' => self::LECTURA,
                    'Caja' => self::LECTURA,
                    'GastoCompartido' => self::LECTURA,
                    'PlanPago' => self::LECTURA,
                    'Cliente' => self::LECTURA,
                    'Proveedor' => self::LECTURA,
                    'OrdenTrabajo' => self::LECTURA,
                    'Empleado' => self::LECTURA,
                    'CategoriaCosto' => self::ALTA,
                    'Auditoria' => self::LECTURA,
                    'TableroUnidades' => self::LECTURA,
                    'CapitalEnPatio' => self::LECTURA,
                    'UnidadesEstancadas' => self::LECTURA,
                ],
                'propios' => ['ver_costos_unidad', 'ver_precio_minimo'],
            ],

            // La planilla del taller y del patio.
            'rrhh' => [
                'modulos' => [
                    'Empleado' => self::COMPLETO,
                    'Sucursal' => self::LECTURA,
                    'User' => self::LECTURA,
                ],
                'propios' => [],
            ],
        ];
    }

    /**
     * Los nombres de permiso que le tocan a un rol, ya existentes en la base.
     *
     * Se filtra contra lo que hay: si Shield todavía no generó un módulo, ese
     * se salta en vez de reventar el alta del concesionario.
     *
     * @return array<int, string>
     */
    public static function para(string $rol): array
    {
        $receta = self::mapa()[$rol] ?? null;

        if ($receta === null) {
            return [];
        }

        $nombres = [];

        foreach ($receta['modulos'] as $modulo => $acciones) {
            foreach ($acciones as $accion) {
                $nombres[] = "{$accion}:{$modulo}";
            }
        }

        $nombres = array_merge($nombres, $receta['propios']);

        return Permission::whereIn('name', $nombres)->pluck('name')->all();
    }

    /** @return array<int, string> */
    public static function roles(): array
    {
        return array_keys(self::mapa());
    }
}
