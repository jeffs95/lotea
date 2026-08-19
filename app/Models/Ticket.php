<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasFactory, PerteneceAEmpresa;

    protected $guarded = ['id'];

    public const ESTADOS = [
        'abierto' => 'Abierto',
        'en_proceso' => 'En proceso',
        'resuelto' => 'Resuelto',
    ];

    protected function casts(): array
    {
        return [
            'contexto' => 'array',
            'respondido_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function respondidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondido_por');
    }

    public function estaResuelto(): bool
    {
        return $this->estado === 'resuelto';
    }

    /** Horas que lleva esperando respuesta. */
    public function getHorasEsperandoAttribute(): ?int
    {
        return $this->respondido_en === null
            ? (int) $this->created_at->diffInHours(now())
            : null;
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', '!=', 'resuelto');
    }
}
