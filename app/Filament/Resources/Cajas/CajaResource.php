<?php

namespace App\Filament\Resources\Cajas;

use App\Filament\Resources\Cajas\Pages\CreateCaja;
use App\Filament\Resources\Cajas\Pages\EditCaja;
use App\Filament\Resources\Cajas\Pages\ListCajas;
use App\Filament\Resources\Cajas\RelationManagers\ArqueosRelationManager;
use App\Filament\Resources\Cajas\RelationManagers\MovimientosRelationManager;
use App\Filament\Resources\Cajas\Schemas\CajaForm;
use App\Filament\Resources\Cajas\Tables\CajasTable;
use App\Models\Caja;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CajaResource extends Resource
{
    protected static ?string $model = Caja::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = 'Dinero';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'cajas';

    protected static ?string $navigationLabel = 'Cajas y bancos';

    protected static ?string $modelLabel = 'caja';

    protected static ?string $pluralModelLabel = 'cajas';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return CajaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CajasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MovimientosRelationManager::class,
            ArqueosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCajas::route('/'),
            'create' => CreateCaja::route('/nueva'),
            'edit' => EditCaja::route('/{record}/editar'),
        ];
    }
}
