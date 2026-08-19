<?php

namespace App\Models\Concerns;

use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Deja constancia de quién tocó qué y cuándo.
 *
 * Lo usan los modelos donde hay dinero. El día que un concesionario diga que
 * alguien le borró un gasto de Q9,000, esto es lo que permite responder con
 * hechos en lugar de con suposiciones.
 *
 * Cada modelo declara en $camposAuditados lo que vale la pena seguir: auditar
 * todas las columnas llena la tabla de ruido y esconde lo que importa.
 */
trait DejaRastro
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->camposAuditados ?? ['*'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->nombreDelRastro());
    }

    /** Cada registro queda marcado con la empresa a la que pertenece. */
    public function tapActivity(Activity $actividad, string $evento): void
    {
        $actividad->empresa_id = $this->empresa_id ?? Tenancy::empresaId();
    }

    public function getDescriptionForEvent(string $evento): string
    {
        $verbo = match ($evento) {
            'created' => 'creó',
            'updated' => 'modificó',
            'deleted' => 'eliminó',
            default => $evento,
        };

        return "{$verbo} ".$this->etiquetaParaRastro();
    }

    /** Cómo se nombra este registro en el rastro. */
    protected function etiquetaParaRastro(): string
    {
        return static::nombreLegible().' '.($this->getKey() ?? '');
    }

    protected function nombreDelRastro(): string
    {
        return static::nombreLegible();
    }

    public static function nombreLegible(): string
    {
        return match (static::class) {
            \App\Models\CostoUnidad::class => 'gasto de unidad',
            \App\Models\GastoCompartido::class => 'gasto compartido',
            \App\Models\Venta::class => 'venta',
            \App\Models\MovimientoCaja::class => 'movimiento de caja',
            \App\Models\PagoCuota::class => 'pago de cuota',
            \App\Models\Cuota::class => 'cuota',
            \App\Models\OrdenTrabajo::class => 'orden de trabajo',
            \App\Models\Unidad::class => 'unidad',
            default => class_basename(static::class),
        };
    }
}
