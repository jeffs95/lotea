<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LecturaIa extends Model
{
    use HasFactory, PerteneceAEmpresa;

    protected $table = 'lecturas_ia';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'costo_usd' => 'decimal:8',
            'exitosa' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getTokensTotalesAttribute(): int
    {
        return $this->tokens_entrada + $this->tokens_salida;
    }

    public function scopeDelMes(Builder $query, ?string $periodo = null): Builder
    {
        $periodo ??= now()->format('Y-m');

        return $query->whereRaw("to_char(created_at, 'YYYY-MM') = ?", [$periodo]);
    }

    public function scopeExitosas(Builder $query): Builder
    {
        return $query->where('exitosa', true);
    }
}
