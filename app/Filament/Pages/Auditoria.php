<?php

namespace App\Filament\Pages;

use App\Models\Rastro;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Quién tocó qué y cuándo, en todo lo que mueve dinero.
 *
 * El día que alguien diga que le borraron un gasto o le cambiaron un precio,
 * esta pantalla responde con hechos. Es solo lectura: el rastro no se edita ni
 * se borra, porque un registro que se puede alterar no prueba nada.
 */
class Auditoria extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Herramientas';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'auditoria';

    protected static ?string $navigationLabel = 'Auditoría';

    protected static ?string $title = 'Quién tocó qué';

    protected string $view = 'filament.pages.auditoria';

    /** Solo quien ve los costos ve el rastro: aquí van montos. */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('ver_costos_unidad') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Rastro::query()->with('causer')->latest('id'))
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Todavía no hay movimientos registrados')
            ->emptyStateDescription('Aquí van a aparecer los cambios en gastos, ventas, caja y unidades.')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Cuándo')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (Rastro $record) => $record->created_at->diffForHumans())
                    ->sortable(),

                TextColumn::make('quien')
                    ->label('Quién')
                    ->badge()
                    ->color(fn (Rastro $record) => $record->causer ? 'primary' : 'gray'),

                TextColumn::make('description')
                    ->label('Qué hizo')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('log_name')
                    ->label('Sobre')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('cambios')
                    ->label('Qué cambió')
                    ->wrap()
                    ->state(fn (Rastro $record) => $record->cambiosLegibles())
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Sobre qué')
                    ->options(fn () => Rastro::query()
                        ->select('log_name')
                        ->distinct()
                        ->orderBy('log_name')
                        ->pluck('log_name', 'log_name')
                        ->all()),

                SelectFilter::make('causer_id')
                    ->label('Quién')
                    ->options(fn () => User::whereIn(
                        'id',
                        Rastro::query()->whereNotNull('causer_id')->distinct()->pluck('causer_id')
                    )->pluck('name', 'id')),

                Filter::make('anulaciones')
                    ->label('Solo anulaciones')
                    ->query(fn (Builder $query) => $query->where('properties', 'like', '%anulad%')),

                Filter::make('hoy')
                    ->label('Solo de hoy')
                    ->query(fn (Builder $query) => $query->whereDate('created_at', today())),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('explicacion')
                ->label('¿Para qué sirve esto?')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('El rastro de lo que pasa con el dinero')
                ->modalDescription(
                    'Cada vez que alguien registra, cambia o anula un gasto, una venta, un movimiento '
                    .'de caja o el precio de una unidad, queda anotado aquí con su nombre y la hora. '
                    .'No se puede editar ni borrar, a propósito: un registro que se puede alterar no '
                    .'prueba nada. Si algún día hay una discusión sobre una cifra, esta es la respuesta.'
                )
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Entendido'),
        ];
    }
}
