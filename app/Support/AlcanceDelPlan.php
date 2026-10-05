<?php

namespace App\Support;

use App\Filament\Pages\Levantamiento;
use App\Filament\Pages\TableroUnidades;
use App\Filament\Pages\TopVendedores;
use App\Filament\Resources\Cajas\CajaResource;
use App\Filament\Resources\CategoriasCosto\CategoriaCostoResource;
use App\Filament\Resources\Clientes\ClienteResource;
use App\Filament\Resources\Creditos\PlanPagoResource;
use App\Filament\Resources\Empleados\EmpleadoResource;
use App\Filament\Resources\GastosCompartidos\GastoCompartidoResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Marcas\MarcaResource;
use App\Filament\Resources\OrdenesTrabajo\OrdenTrabajoResource;
use App\Filament\Resources\Proveedores\ProveedorResource;
use App\Filament\Resources\Unidades\UnidadResource;
use App\Filament\Resources\Ventas\VentaResource;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\Unidad;

/**
 * Qué puede usar cada concesionario según lo que paga.
 *
 * Hasta ahora los planes eran un precio y una lista bonita: los módulos se
 * guardaban pero solo la lectura con IA los miraba, y los topes de sucursales,
 * usuarios y unidades no los verificaba nadie. Un cliente del plan más barato
 * tenía el sistema entero, así que el plan de en medio no se sostenía solo.
 *
 * Aquí viven las dos reglas —qué pantalla pide qué módulo, y qué tope aplica a
 * qué— para que se puedan cambiar en un sitio y no repartidas por quince
 * recursos.
 */
class AlcanceDelPlan
{
    /**
     * Qué módulo exige cada pantalla.
     *
     * Lo que no está en esta lista está siempre disponible: las sucursales,
     * los usuarios, la marca y los tickets de soporte no se le cobran a nadie
     * aparte, porque sin eso no se puede ni configurar el sistema.
     *
     * El mapa es de nombres de clase a propósito: un recurso que se renombre
     * deja de encontrarse aquí y queda abierto, que es el error barato. Al
     * revés —cerrarse solo— un cliente perdería una pantalla que paga sin que
     * nadie lo haya decidido.
     *
     * @var array<string, string>
     */
    public const POR_PANTALLA = [
        // El inventario y lo que lo alimenta.
        UnidadResource::class => 'unidades',
        MarcaResource::class => 'unidades',
        ProveedorResource::class => 'unidades',
        TableroUnidades::class => 'unidades',
        Levantamiento::class => 'unidades',

        // Traer el carro de la subasta y repartir los fletes.
        GastoCompartidoResource::class => 'importacion',

        // Lo que cuesta cada unidad de verdad.
        CategoriaCostoResource::class => 'costeo',

        // Vender: el cliente, el prospecto, la venta y la caja que la cobra.
        VentaResource::class => 'ventas',
        ClienteResource::class => 'ventas',
        LeadResource::class => 'ventas',
        CajaResource::class => 'ventas',

        // Lo que se le paga a cada vendedor.
        TopVendedores::class => 'comisiones',

        // El crédito que da la casa.
        PlanPagoResource::class => 'cartera',

        // El taller propio.
        OrdenTrabajoResource::class => 'taller',

        // La planilla.
        EmpleadoResource::class => 'nomina',
    ];

    /** Qué cuenta cada tope del plan. */
    public const TOPES = [
        'sucursales' => 'max_sucursales',
        'usuarios' => 'max_usuarios',
        'unidades' => 'max_unidades_activas',
    ];

    /**
     * ¿Esta empresa puede abrir esta pantalla?
     *
     * Sin plan asignado no se le cierra nada. Una empresa sin plan es un
     * descuido de configuración, no un cliente que dejó de pagar, y dejarla
     * sin sistema entero por eso hace más daño que el que evita.
     */
    public static function permitePantalla(?Empresa $empresa, string $pantalla): bool
    {
        $modulo = static::POR_PANTALLA[$pantalla] ?? null;

        if ($modulo === null || $empresa === null) {
            return true;
        }

        return $empresa->puedeUsarModulo($modulo);
    }

    /** Cuántos lleva de lo que se cuenta: sucursales, usuarios o unidades. */
    public static function cuantosTiene(Empresa $empresa, string $que): int
    {
        return match ($que) {
            'sucursales' => Tenancy::comoEmpresa($empresa, fn () => Sucursal::count()),
            'usuarios' => $empresa->usuarios()->count(),
            // Las vendidas y las dadas de baja no ocupan lugar en el patio:
            // el tope es de lo que se está administrando hoy, no del histórico.
            'unidades' => Tenancy::comoEmpresa($empresa, fn () => Unidad::enInventario()->count()),
            default => 0,
        };
    }

    /** El tope del plan, o null si no tiene. */
    public static function topeDe(?Empresa $empresa, string $que): ?int
    {
        $columna = static::TOPES[$que] ?? null;

        if ($columna === null || $empresa?->plan === null) {
            return null;
        }

        return $empresa->plan->{$columna};
    }

    /** ¿Le queda lugar para uno más? */
    public static function puedeAgregar(?Empresa $empresa, string $que): bool
    {
        $tope = static::topeDe($empresa, $que);

        return $tope === null || static::cuantosTiene($empresa, $que) < $tope;
    }

    /** Lo que se le dice cuando ya no le queda. */
    public static function avisoDeTope(Empresa $empresa, string $que): string
    {
        $tope = static::topeDe($empresa, $que);
        $plan = $empresa->plan?->nombre ?? 'actual';

        return match ($que) {
            'sucursales' => "El plan {$plan} llega hasta {$tope} ".($tope === 1 ? 'sucursal' : 'sucursales').'.',
            'usuarios' => "El plan {$plan} llega hasta {$tope} usuarios.",
            'unidades' => "El plan {$plan} llega hasta {$tope} unidades en inventario al mismo tiempo.",
            default => "El plan {$plan} no permite agregar más.",
        };
    }
}
