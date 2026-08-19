<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Arqueo extends Model
{
    use HasFactory, PerteneceAEmpresa;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'realizado_en' => 'datetime',
            'saldo_sistema' => 'decimal:2',
            'saldo_contado' => 'decimal:2',
            'diferencia' => 'decimal:2',
        ];
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cuadro(): bool
    {
        return bccomp((string) $this->diferencia, '0.00', 2) === 0;
    }

    /** Falta dinero: es lo que hay que explicar. */
    public function hayFaltante(): bool
    {
        return bccomp((string) $this->diferencia, '0.00', 2) < 0;
    }
}
