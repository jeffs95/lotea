<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtLinea extends Model
{
    use HasFactory, PerteneceAEmpresa;

    protected $table = 'ot_lineas';

    protected $guarded = ['id'];

    public const TIPOS = [
        'mano_obra' => 'Mano de obra',
        'repuesto' => 'Repuesto',
        'tercero' => 'Trabajo a terceros',
    ];

    /** A qué categoría de costo se descarga cada tipo al cerrar la orden. */
    public const CATEGORIAS = [
        'mano_obra' => 'mano_obra',
        'repuesto' => 'repuestos',
        'tercero' => 'trabajos_terceros',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_trabajo_id');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function esManoDeObra(): bool
    {
        return $this->tipo === 'mano_obra';
    }

    /** La unidad de la cantidad: horas para el mecánico, piezas para el repuesto. */
    public function getUnidadCantidadAttribute(): string
    {
        return $this->esManoDeObra() ? 'h' : 'u';
    }
}
