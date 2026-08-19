<?php

namespace App\Filament\Central\Widgets;

use App\Models\Empresa;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;

/**
 * Los clientes que dejaron de entrar.
 *
 * Un concesionario que no abre el sistema en dos semanas ya volvió a su Excel;
 * es un churn anunciado y todavía se puede llamar a tiempo.
 */
class ClientesEnRiesgo extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Clientes que dejaron de entrar';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn () => Empresa::query()
                    ->where('activa', true)
                    ->whereDoesntHave('usuarios', fn ($q) => $q->where('ultimo_acceso_at', '>=', now()->subDays(14)))
                    ->with('plan')
            )
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Todos entrando')
            ->emptyStateDescription('Ningún cliente lleva más de dos semanas sin abrir el sistema.')
            ->columns([
                TextColumn::make('nombre_comercial')
                    ->label('Concesionario')
                    ->weight('bold')
                    ->state(fn (Empresa $record) => $record->nombre_comercial ?: $record->nombre),

                TextColumn::make('plan.nombre')->label('Plan')->badge()->placeholder('Sin plan'),

                TextColumn::make('ultimo_acceso')
                    ->label('Última vez')
                    ->state(fn (Empresa $record) => $record->usuarios()->max('ultimo_acceso_at'))
                    ->placeholder('Nunca entró')
                    ->since()
                    ->color('danger'),

                TextColumn::make('contacto_telefono')
                    ->label('A quién llamar')
                    ->placeholder('—')
                    ->description(fn (Empresa $record) => $record->contacto_nombre),

                TextColumn::make('mensualidad')
                    ->label('En riesgo')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd(),
            ]);
    }
}
