<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use App\Support\Coordenadas;
use App\Support\WhatsApp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sucursal extends Model
{
    use HasFactory, PerteneceAEmpresa, SoftDeletes;

    protected $table = 'sucursales';

    protected $guarded = ['id'];

    /** Los defaults de la base, para que no salgan como cambios fantasma. */
    protected $attributes = [
        'activa' => true,
        'es_principal' => false,
        'mostrar_en_portal' => true,
    ];

    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
            'activa' => 'boolean',
            'mostrar_en_portal' => 'boolean',
            'latitud' => 'decimal:7',
            'longitud' => 'decimal:7',
        ];
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    /** Las que el concesionario quiere que la gente encuentre. */
    public function scopeEnElPortal(Builder $query): Builder
    {
        return $query->activas()->where('mostrar_en_portal', true);
    }

    public function tieneUbicacion(): bool
    {
        return filled($this->latitud) && filled($this->longitud);
    }

    /**
     * El punto como «lat,lng», sin los ceros que agrega el cast decimal.
     *
     * Va en la URL del mapa incrustado, y 14.6231000 en vez de 14.6231 se ve
     * descuidado en algo que el visitante puede copiar.
     */
    public function getPuntoAttribute(): ?string
    {
        return $this->tieneUbicacion()
            ? ((float) $this->latitud).','.((float) $this->longitud)
            : null;
    }

    /** Para el que va a buscar el patio en el mapa. */
    public function getMapaGoogleAttribute(): ?string
    {
        return $this->tieneUbicacion()
            ? Coordenadas::google((float) $this->latitud, (float) $this->longitud)
            : null;
    }

    /** Waze, que es con lo que se maneja aquí. */
    public function getMapaWazeAttribute(): ?string
    {
        return $this->tieneUbicacion()
            ? Coordenadas::waze((float) $this->latitud, (float) $this->longitud)
            : null;
    }

    /**
     * El número de WhatsApp en el formato que espera wa.me: solo dígitos y con
     * código de país. Si lo escribieron como 5555-1234, se le antepone el 502.
     */
    public function getWhatsappInternacionalAttribute(): ?string
    {
        return WhatsApp::internacional($this->whatsapp ?: $this->telefono);
    }
}
