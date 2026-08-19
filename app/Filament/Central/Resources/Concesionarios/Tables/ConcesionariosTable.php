<?php

namespace App\Filament\Central\Resources\Concesionarios\Tables;

use App\Actions\SuspenderConcesionario;
use App\Models\Empresa;
use App\Support\Tenancy;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConcesionariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre_comercial')
                    ->label('Concesionario')
                    ->weight('bold')
                    ->searchable(['nombre', 'nombre_comercial'])
                    ->description(fn (Empresa $record) => $record->slug)
                    ->state(fn (Empresa $record) => $record->nombre_comercial ?: $record->nombre),

                TextColumn::make('plan.nombre')
                    ->label('Plan')
                    ->badge()
                    ->color('primary')
                    ->placeholder('Sin plan')
                    ->sortable(),

                TextColumn::make('estado_suscripcion')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'activa' => 'Al día',
                        'suspendida' => 'Suspendida',
                        'baja' => 'De baja',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'activa' => 'success',
                        'suspendida' => 'warning',
                        'baja' => 'danger',
                    })
                    ->description(fn (Empresa $record) => $record->motivo_suspension),

                TextColumn::make('unidades_count')
                    ->label('Unidades')
                    ->counts('unidades')
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('usuarios_count')
                    ->label('Usuarios')
                    ->counts('usuarios')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                // Un cliente que no entra hace semanas es un churn en camino.
                TextColumn::make('ultimo_acceso')
                    ->label('Último acceso')
                    ->state(fn (Empresa $record) => $record->usuarios()->max('ultimo_acceso_at'))
                    ->placeholder('Nunca entró')
                    ->since()
                    ->color(fn ($state) => match (true) {
                        $state === null => 'danger',
                        \Illuminate\Support\Carbon::parse($state)->lt(now()->subDays(14)) => 'warning',
                        default => null,
                    }),

                // Sin summarizer: la mensualidad es un accesor y no una
                // columna, así que no se puede sumar en SQL. El MRR total está
                // en el escritorio.
                // Lo que te está costando el add-on este mes, cliente por cliente.
                TextColumn::make('consumo_ia')
                    ->label('IA este mes')
                    ->alignEnd()
                    ->state(fn (Empresa $record) => $record->tieneModulo('ia')
                        ? $record->lecturasIaDelMes()
                        : null)
                    ->placeholder('sin el módulo')
                    ->formatStateUsing(function (?int $state, Empresa $record) {
                        if ($state === null) {
                            return null;
                        }

                        $tope = $record->plan?->max_lecturas_ia;

                        return $tope ? "{$state} / {$tope}" : (string) $state;
                    })
                    ->description(fn (Empresa $record) => $record->tieneModulo('ia')
                        ? 'Q '.number_format(\App\Support\TarifaDeIa::enQuetzales($record->costoIaDelMes()), 2).' de costo'
                        : null)
                    ->color(function (?int $state, Empresa $record) {
                        $tope = $record->plan?->max_lecturas_ia;

                        return $tope && $state !== null && $state >= $tope * 0.9 ? 'warning' : null;
                    })
                    ->toggleable(),

                TextColumn::make('mensualidad')
                    ->label('Mensualidad')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->color(fn (Empresa $record) => $record->puedeOperar() ? null : 'gray'),
            ])
            ->filters([
                SelectFilter::make('plan_id')->label('Plan')->relationship('plan', 'nombre'),

                Filter::make('suspendidas')
                    ->label('Solo suspendidas')
                    ->query(fn ($query) => $query->whereNotNull('suspendida_en')),

                Filter::make('sin_actividad')
                    ->label('Sin entrar hace 14 días')
                    ->query(fn ($query) => $query->whereDoesntHave(
                        'usuarios',
                        fn ($q) => $q->where('ultimo_acceso_at', '>=', now()->subDays(14)),
                    )),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),

                    Action::make('abrirPanel')
                        ->label('Abrir su panel')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn (Empresa $record) => route('filament.admin.pages.dashboard', ['tenant' => $record->slug]))
                        ->openUrlInNewTab(),

                    Action::make('verPortal')
                        ->label('Ver su portal')
                        ->icon('heroicon-o-globe-alt')
                        ->url(fn (Empresa $record) => \App\Support\PortalUrl::inicio($record))
                        ->openUrlInNewTab(),

                    Action::make('suspender')
                        ->label('Suspender')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->visible(fn (Empresa $record) => ! $record->estaSuspendida())
                        ->schema([
                            Textarea::make('motivo')
                                ->label('¿Por qué se suspende?')
                                ->required()
                                ->rows(2)
                                ->placeholder('Mensualidad de agosto sin pagar'),
                        ])
                        ->action(function (Empresa $record, array $data) {
                            try {
                                app(SuspenderConcesionario::class)->suspender($record, $data['motivo']);

                                Notification::make()
                                    ->title('Concesionario suspendido')
                                    ->body('Sus datos quedan intactos; al reactivarlo siguen donde estaban.')
                                    ->success()
                                    ->send();
                            } catch (DomainException $e) {
                                Notification::make()->title($e->getMessage())->danger()->send();
                            }
                        }),

                    Action::make('reactivar')
                        ->label('Reactivar')
                        ->icon('heroicon-o-play-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('Vuelve a tener acceso completo al sistema.')
                        ->visible(fn (Empresa $record) => $record->estaSuspendida())
                        ->action(function (Empresa $record) {
                            app(SuspenderConcesionario::class)->reactivar($record);

                            Notification::make()->title('Concesionario reactivado')->success()->send();
                        }),
                ]),
            ]);
    }
}
