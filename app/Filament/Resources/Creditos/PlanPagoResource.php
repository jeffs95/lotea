<?php

namespace App\Filament\Resources\Creditos;

use App\Filament\Resources\Creditos\Pages\EditPlanPago;
use App\Filament\Resources\Creditos\Pages\ListPlanesPago;
use App\Filament\Resources\Creditos\RelationManagers\CuotasRelationManager;
use App\Filament\Resources\Creditos\Schemas\PlanPagoForm;
use App\Filament\Resources\Creditos\Tables\PlanesPagoTable;
use App\Models\PlanPago;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PlanPagoResource extends Resource
{
    protected static ?string $model = PlanPago::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Dinero';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'creditos';

    protected static ?string $navigationLabel = 'Cartera';

    protected static ?string $modelLabel = 'crédito';

    protected static ?string $pluralModelLabel = 'créditos';

    protected static ?string $recordTitleAttribute = 'numero';

    /** En rojo los que están atrasados: es la plata que hay que ir a cobrar. */
    public static function getNavigationBadge(): ?string
    {
        $enMora = PlanPago::vigentes()
            ->whereHas('cuotas', fn ($q) => $q->vencidas())
            ->count();

        return $enMora > 0 ? (string) $enMora : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return PlanPagoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlanesPagoTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [CuotasRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlanesPago::route('/'),
            'edit' => EditPlanPago::route('/{record}'),
        ];
    }
}
