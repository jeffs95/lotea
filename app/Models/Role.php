<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as RoleBase;

/**
 * Los roles son por empresa (spatie los llama "teams"). Esta subclase existe
 * para darle a Filament la relación que necesita para scopear la pantalla de
 * Roles al tenant activo.
 */
class Role extends RoleBase
{
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
