<?php

namespace App\Models;

use App\Support\ModoSoporte;
use App\Support\Tenancy;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'telefono',
        'activo',
        'es_operador',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'es_operador' => 'boolean',
        ];
    }

    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class)
            // Lo acordado es entre este vendedor y este concesionario: la misma
            // persona podría vender para dos y haber pactado distinto.
            ->withPivot('comision_por_venta')
            ->withTimestamps();
    }

    /**
     * Lo que este concesionario le paga por carro vendido.
     *
     * Es el valor de arranque de una venta nueva, no una atadura: en la venta
     * se puede escribir otro monto porque algunos carros se negocian aparte.
     */
    public function comisionAcordadaCon(?Empresa $empresa = null): ?string
    {
        $empresa ??= Tenancy::hayEmpresa() ? Tenancy::empresa() : null;

        if (! $empresa) {
            return null;
        }

        $relacion = $this->empresas->firstWhere('id', $empresa->getKey())
            ?? $this->empresas()->whereKey($empresa->getKey())->first();

        $acordado = $relacion?->pivot?->comision_por_venta;

        return filled($acordado) ? (string) $acordado : null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->activo) {
            return false;
        }

        // El panel central es de Lotea, no de los concesionarios. El acceso
        // sale de una bandera propia y no de un rol, porque los roles los
        // administra el cliente dentro de su propia empresa.
        if ($panel->getId() === 'central') {
            return (bool) $this->es_operador;
        }

        return true;
    }

    /**
     * Empresas entre las que puede cambiar con el selector del panel.
     *
     * Una suspendida no aparece: la suspensión es la palanca de cobro y si no
     * corta el acceso no sirve de nada.
     */
    public function getTenants(Panel $panel): array|Collection
    {
        $suyas = $this->empresas()
            ->where('activa', true)
            ->whereNull('suspendida_en')
            ->get();

        // Durante una sesión de soporte, ese concesionario aparece en el
        // selector aunque el operador no sea de la casa.
        $soporte = ModoSoporte::empresa();

        return $soporte && ! $suyas->contains('id', $soporte->getKey())
            ? $suyas->push($soporte)
            : $suyas;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if ($tenant instanceof Empresa && ! $tenant->puedeOperar()) {
            return false;
        }

        // Lotea entrando a dar soporte: no es de la empresa, pero abrió su
        // panel a propósito y desde el central.
        if (ModoSoporte::esLaEmpresaAbierta($tenant->getKey())) {
            return true;
        }

        return $this->empresas()->whereKey($tenant->getKey())->exists();
    }
}
