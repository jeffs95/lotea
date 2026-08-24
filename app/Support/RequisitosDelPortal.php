<?php

namespace App\Support;

use App\Enums\EstadoUnidad;

/**
 * Qué le falta a una unidad para que un comprador la vea en el portal.
 *
 * Vive aparte del modelo porque el formulario de alta necesita responder esto
 * antes de que la unidad exista: mientras el usuario escribe, con lo que hay
 * en pantalla y nada guardado todavía.
 *
 * Hay dos clases de faltante y no dan lo mismo:
 *
 * - Las **trabas**: sin precio o sin una foto el sistema apaga «Publicado» al
 *   guardar. Un carro sin precio ni foto en el portal hace quedar mal al
 *   concesionario, así que no se publica y punto.
 * - La **espera**: el estado. Un carro recién comprado no se ofrece como
 *   disponible. Aquí el interruptor sí se respeta y la unidad aparece sola en
 *   cuanto avanza de estado, sin que nadie tenga que volver a marcarla.
 */
class RequisitosDelPortal
{
    /**
     * Lo que impide publicar y hay que llenar sí o sí.
     *
     * @return array<int, string>
     */
    public static function trabas(mixed $precio, bool $tieneFoto): array
    {
        $falta = [];

        if (blank($precio) || (float) $precio <= 0) {
            $falta[] = 'el precio de lista';
        }

        if (! $tieneFoto) {
            $falta[] = 'al menos una foto';
        }

        return $falta;
    }

    /** ¿El estado deja que el carro se ofrezca como disponible? */
    public static function elEstadoAdmiteVenta(mixed $estado): bool
    {
        $estado = $estado instanceof EstadoUnidad
            ? $estado
            : EstadoUnidad::tryFrom((string) $estado);

        return (bool) $estado?->admitePreventa();
    }

    /**
     * Todo lo que falta, en una frase por punto, listo para mostrar.
     *
     * @return array<int, string>
     */
    public static function faltantes(mixed $precio, bool $tieneFoto, mixed $estado): array
    {
        $falta = static::trabas($precio, $tieneFoto);

        if (! static::elEstadoAdmiteVenta($estado)) {
            $etiqueta = ($estado instanceof EstadoUnidad
                ? $estado
                : EstadoUnidad::tryFrom((string) $estado))?->getLabel();

            $falta[] = $etiqueta
                ? "avanzar el estado: en «{$etiqueta}» todavía no se ofrece"
                : 'un estado que admita venta';
        }

        return $falta;
    }

    /** Los estados en los que el carro sí se muestra, para explicarlo. */
    public static function estadosQueSeVen(): string
    {
        return collect(EstadoUnidad::cases())
            ->filter->admitePreventa()
            ->map->getLabel()
            ->join(', ', ' o ');
    }
}
