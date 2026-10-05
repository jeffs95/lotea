<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use App\Support\AlmacenDeArchivos;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Cliente extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, PerteneceAEmpresa, SoftDeletes;

    protected $guarded = ['id'];

    public const TIPOS = ['persona' => 'Persona individual', 'empresa' => 'Empresa'];

    /**
     * El documento de identidad del comprador.
     *
     * Hace falta porque el traspaso de propiedad queda a nombre de quien
     * compra: si años después aparece un problema con ese vehículo —una multa,
     * un embargo, un accidente— lo primero que preguntan es a quién se le
     * vendió, y el número escrito a mano no prueba nada.
     *
     * Al disco privado, nunca al que sirve el CDN: esto es un documento de
     * identidad, no una foto de un carro. Sale solo con enlace firmado y para
     * quien pertenece al concesionario.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('identificacion')
            ->useDisk(AlmacenDeArchivos::discoPrivado());
    }

    /** ¿Quedó respaldado quién compró, o solo el número escrito? */
    public function tieneIdentificacion(): bool
    {
        return $this->getMedia('identificacion')->isNotEmpty();
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function getEtiquetaAttribute(): string
    {
        return $this->nit ? "{$this->nombre} · NIT {$this->nit}" : $this->nombre;
    }
}
