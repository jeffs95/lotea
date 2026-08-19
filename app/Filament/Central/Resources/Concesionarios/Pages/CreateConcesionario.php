<?php

namespace App\Filament\Central\Resources\Concesionarios\Pages;

use App\Actions\AltaDeConcesionario;
use App\Filament\Central\Resources\Concesionarios\ConcesionarioResource;
use App\Models\Plan;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateConcesionario extends CreateRecord
{
    protected static string $resource = ConcesionarioResource::class;

    protected static ?string $title = 'Dar de alta un concesionario';

    /**
     * El alta no es un insert: siembra catálogos, roles, la sucursal
     * principal, el usuario dueño y el primer cobro.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $dueno = [
            'name' => $data['dueno_nombre'],
            'email' => $data['dueno_email'],
            'telefono' => $data['dueno_telefono'] ?? null,
            'password' => $data['dueno_password'],
        ];

        $sucursal = $data['sucursal_principal'] ?? 'Casa matriz';

        foreach (['dueno_nombre', 'dueno_email', 'dueno_telefono', 'dueno_password', 'sucursal_principal'] as $campo) {
            unset($data[$campo]);
        }

        $empresa = app(AltaDeConcesionario::class)->ejecutar(
            [...$data, 'sucursal_principal' => $sucursal],
            $dueno,
            $data['plan_id'] ? Plan::find($data['plan_id']) : null,
        );

        Notification::make()
            ->title('Concesionario dado de alta')
            ->body("Ya puede entrar en /app/{$empresa->slug} con {$dueno['email']}.")
            ->success()
            ->send();

        return $empresa;
    }
}
