<?php

namespace App\Filament\Resources\Unidades;

use App\Filament\Resources\Unidades\Pages\CreateUnidad;
use App\Filament\Resources\Unidades\Pages\EditUnidad;
use App\Filament\Resources\Unidades\Pages\ListUnidades;
use App\Filament\Resources\Unidades\Pages\RentabilidadUnidad;
use App\Filament\Resources\Unidades\RelationManagers\CostosRelationManager;
use App\Filament\Resources\Unidades\RelationManagers\TransicionesRelationManager;
use App\Filament\Resources\Unidades\Schemas\UnidadForm;
use App\Filament\Resources\Unidades\Tables\UnidadesTable;
use App\Models\Unidad;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UnidadResource extends Resource
{
    protected static ?string $model = Unidad::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'unidades';

    protected static ?string $navigationLabel = 'Unidades';

    protected static ?string $modelLabel = 'unidad';

    protected static ?string $pluralModelLabel = 'unidades';

    protected static ?string $recordTitleAttribute = 'stock_no';

    public static function form(Schema $schema): Schema
    {
        return UnidadForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnidadesTable::configure($table);
    }

    /** Pestañas dentro de la ficha de una unidad. */
    public static function getRecordSubNavigation(\Filament\Resources\Pages\Page $page): array
    {
        return $page->generateNavigationItems([
            EditUnidad::class,
            RentabilidadUnidad::class,
        ]);
    }

    public static function getRelations(): array
    {
        return [
            CostosRelationManager::class,
            TransicionesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnidades::route('/'),
            'create' => CreateUnidad::route('/nueva'),
            'edit' => EditUnidad::route('/{record}/editar'),
            'rentabilidad' => RentabilidadUnidad::route('/{record}/rentabilidad'),
        ];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return "{$record->stock_no} · {$record->descripcion}";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['stock_no', 'vin'];
    }
}
