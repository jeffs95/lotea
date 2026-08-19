<?php

namespace App\Filament\Resources\OrdenesTrabajo;

use App\Filament\Resources\OrdenesTrabajo\Pages\CreateOrdenTrabajo;
use App\Filament\Resources\OrdenesTrabajo\Pages\EditOrdenTrabajo;
use App\Filament\Resources\OrdenesTrabajo\Pages\ListOrdenesTrabajo;
use App\Filament\Resources\OrdenesTrabajo\RelationManagers\LineasRelationManager;
use App\Filament\Resources\OrdenesTrabajo\Schemas\OrdenTrabajoForm;
use App\Filament\Resources\OrdenesTrabajo\Tables\OrdenesTrabajoTable;
use App\Models\OrdenTrabajo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class OrdenTrabajoResource extends Resource
{
    protected static ?string $model = OrdenTrabajo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Taller';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'ordenes-trabajo';

    protected static ?string $navigationLabel = 'Órdenes de trabajo';

    protected static ?string $modelLabel = 'orden de trabajo';

    protected static ?string $pluralModelLabel = 'órdenes de trabajo';

    protected static ?string $recordTitleAttribute = 'numero';

    public static function getNavigationBadge(): ?string
    {
        $abiertas = OrdenTrabajo::abiertas()->count();

        return $abiertas > 0 ? (string) $abiertas : null;
    }

    public static function form(Schema $schema): Schema
    {
        return OrdenTrabajoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdenesTrabajoTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [LineasRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrdenesTrabajo::route('/'),
            'create' => CreateOrdenTrabajo::route('/nueva'),
            'edit' => EditOrdenTrabajo::route('/{record}/editar'),
        ];
    }
}
