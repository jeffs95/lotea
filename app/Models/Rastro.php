<?php

namespace App\Models;

use App\Models\Scopes\EmpresaScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * El rastro de auditoría, para leerlo.
 *
 * Extiende el modelo de spatie solo para agregarle el filtro por empresa: la
 * escritura la sigue haciendo el paquete con su propio modelo. Lleva el scope
 * a mano y no el trait completo porque ese exige empresa al crear, y aquí no se
 * crea nada.
 */
class Rastro extends Activity
{
    protected static function booted(): void
    {
        static::addGlobalScope(new EmpresaScope);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** Los campos que cambiaron, con su valor viejo y el nuevo. */
    public function cambios(): array
    {
        $nuevos = $this->properties['attributes'] ?? [];
        $viejos = $this->properties['old'] ?? [];

        return collect($nuevos)
            ->map(fn ($valor, $campo) => [
                'campo' => $campo,
                'antes' => $viejos[$campo] ?? null,
                'despues' => $valor,
            ])
            ->values()
            ->all();
    }

    /**
     * El cambio escrito como se lee, no como lo guarda la base.
     *
     * Un "publicado: 1 →" no le dice nada a nadie; "Publicado: sí → no" sí.
     */
    public function cambiosLegibles(): string
    {
        return collect($this->cambios())
            ->map(function (array $cambio) {
                $campo = self::nombreDelCampo($cambio['campo']);
                $antes = self::valorLegible($cambio['antes']);
                $despues = self::valorLegible($cambio['despues']);

                return $cambio['antes'] === null && $this->event === 'created'
                    ? "{$campo}: {$despues}"
                    : "{$campo}: {$antes} → {$despues}";
            })
            ->implode(' · ');
    }

    protected static function valorLegible(mixed $valor): string
    {
        return match (true) {
            $valor === null, $valor === '' => 'vacío',
            $valor === true, $valor === 1, $valor === '1' => 'sí',
            $valor === false, $valor === 0, $valor === '0' => 'no',
            is_string($valor) && strlen($valor) > 24 => Str::limit($valor, 24),
            default => (string) $valor,
        };
    }

    /** Nombres de campo en español, para no mostrar la columna cruda. */
    protected static function nombreDelCampo(string $campo): string
    {
        return match ($campo) {
            'monto_base' => 'monto en quetzales',
            'costo_total' => 'costo total',
            'precio_lista' => 'precio de lista',
            'precio_minimo' => 'precio mínimo',
            'precio_venta' => 'precio de venta',
            'precio_final' => 'precio final',
            'anulado_en' => 'anulado',
            'anulada_en' => 'anulada',
            'motivo_anulacion' => 'motivo',
            'comision_monto' => 'comisión',
            'es_presupuesto' => 'es presupuesto',
            'tipo_cambio' => 'tipo de cambio',
            'categoria_costo_id' => 'categoría',
            'costos_descargados' => 'costos descargados',
            'total_mano_obra' => 'mano de obra',
            'total_repuestos' => 'repuestos',
            'total_terceros' => 'terceros',
            'forma_pago' => 'forma de pago',
            default => str_replace('_', ' ', $campo),
        };
    }

    public function getQuienAttribute(): string
    {
        return $this->causer?->name ?? 'el sistema';
    }
}
