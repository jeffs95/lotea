<?php

namespace App\Actions;

use App\Models\CategoriaCosto;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Da de alta un concesionario nuevo, listo para operar.
 *
 * Un tenant vacío es un tenant inservible: sin categorías de costo no se puede
 * registrar un gasto, y sin sucursal no se puede recibir una unidad. Por eso
 * el alta siembra ambas cosas en la misma transacción.
 */
class CrearEmpresa
{
    /** Las categorías con las que arranca todo cliente. */
    public const CATEGORIAS_BASE = [
        // codigo, nombre, grupo, afecta_costo, prorrateable
        ['precio_compra', 'Precio de compra (martillo)', 'compra', true, false],
        ['fees_subasta', 'Fees de subasta', 'compra', true, false],
        ['gate_fee', 'Gate fee', 'compra', true, false],
        ['tramite_titulo', 'Trámite de título', 'compra', true, false],

        ['transporte_usa', 'Transporte terrestre USA', 'importacion', true, true],
        ['bodegaje_usa', 'Bodegaje y handling USA', 'importacion', true, true],
        ['flete_maritimo', 'Flete marítimo', 'importacion', true, true],
        ['seguro_carga', 'Seguro de carga', 'importacion', true, true],
        ['iprima', 'IPRIMA', 'importacion', true, false],
        ['dai', 'DAI', 'importacion', true, false],
        ['iva_importacion', 'IVA de importación', 'importacion', true, false],
        ['honorarios_agente', 'Honorarios de agente aduanal', 'importacion', true, true],
        ['almacenaje', 'Almacenaje y demoras', 'importacion', true, true],
        ['transporte_local', 'Transporte local', 'importacion', true, true],

        ['repuestos', 'Repuestos', 'taller', true, false],
        ['mano_obra', 'Mano de obra', 'taller', true, false],
        ['pintura', 'Pintura y enderezado', 'taller', true, false],
        ['trabajos_terceros', 'Trabajos a terceros', 'taller', true, false],

        ['detallado', 'Detallado y limpieza', 'venta', true, false],
        ['publicidad', 'Publicidad', 'venta', false, false],
        ['comision_vendedor', 'Comisión de vendedor', 'venta', false, false],
        ['papeleria_traspaso', 'Papelería y traspaso', 'venta', true, false],

        ['otros', 'Otros gastos', 'otros', true, false],
    ];

    /** Roles con los que nace toda empresa. Los permisos los reparte Shield. */
    public const ROLES_BASE = [
        'dueno' => 'Dueño',
        'gerente_sucursal' => 'Gerente de sucursal',
        'comprador' => 'Comprador (subastas)',
        'coordinador_importaciones' => 'Coordinador de importaciones',
        'jefe_taller' => 'Jefe de taller',
        'mecanico' => 'Mecánico',
        'vendedor' => 'Vendedor',
        'cajero' => 'Cajero',
        'contador' => 'Contador',
        'rrhh' => 'Recursos humanos',
    ];

    public function ejecutar(array $datos, string $sucursalPrincipal = 'Casa matriz'): Empresa
    {
        return DB::transaction(function () use ($datos, $sucursalPrincipal) {
            $empresa = Empresa::create([
                ...$datos,
                'slug' => $datos['slug'] ?? Str::slug($datos['nombre_comercial'] ?? $datos['nombre']),
            ]);

            // El alta corre desde consola o desde el panel central, donde no hay
            // empresa activa: la fijamos para que el trait rellene empresa_id.
            Tenancy::comoEmpresa($empresa, function () use ($empresa, $sucursalPrincipal) {
                Sucursal::create([
                    'codigo' => 'PRIN',
                    'nombre' => $sucursalPrincipal,
                    'es_principal' => true,
                ]);

                foreach (self::CATEGORIAS_BASE as $orden => [$codigo, $nombre, $grupo, $afecta, $prorrateable]) {
                    CategoriaCosto::create([
                        'codigo' => $codigo,
                        'nombre' => $nombre,
                        'grupo' => $grupo,
                        'afecta_costo' => $afecta,
                        'prorrateable' => $prorrateable,
                        'orden' => ($orden + 1) * 10,
                        'es_sistema' => true,
                    ]);
                }

                foreach (array_keys(self::ROLES_BASE) as $rol) {
                    Role::findOrCreate($rol, 'web');
                }

                // El dueño ve y hace todo dentro de su empresa; el resto de
                // roles nace vacío y se arma desde la pantalla de Roles.
                Role::findOrCreate('dueno', 'web')->syncPermissions(Permission::all());
            });

            return $empresa;
        });
    }
}
