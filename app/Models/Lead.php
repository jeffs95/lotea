<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use HasFactory, PerteneceAEmpresa;

    protected $guarded = ['id'];

    public const ORIGENES = [
        'portal' => 'Portal web',
        'whatsapp' => 'WhatsApp',
        'facebook' => 'Facebook',
        'referido' => 'Referido',
        'mostrador' => 'Mostrador',
    ];

    public const ESTADOS = [
        'nuevo' => 'Nuevo',
        'contactado' => 'Contactado',
        'cotizado' => 'Cotizado',
        'visita' => 'Visita agendada',
        'ganado' => 'Ganado',
        'perdido' => 'Perdido',
    ];

    protected function casts(): array
    {
        return [
            'primera_respuesta_en' => 'datetime',
            'ultimo_contacto_en' => 'datetime',
        ];
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    /** Minutos que tardaron en contestarle. Null = todavía nadie le responde. */
    public function getMinutosDeRespuestaAttribute(): ?int
    {
        return $this->primera_respuesta_en
            ? (int) $this->created_at->diffInMinutes($this->primera_respuesta_en)
            : null;
    }

    public function estaSinAtender(): bool
    {
        return $this->primera_respuesta_en === null;
    }
}
