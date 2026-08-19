<?php

namespace App\Filament\Resources\Sucursales;

use App\Filament\Resources\Sucursales\Pages\CreateSucursal;
use App\Filament\Resources\Sucursales\Pages\EditSucursal;
use App\Filament\Resources\Sucursales\Pages\ListSucursales;
use App\Filament\Resources\Sucursales\Schemas\SucursalForm;
use App\Filament\Resources\Sucursales\Tables\SucursalesTable;
use App\Models\Sucursal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SucursalResource extends Resource
{
    protected static ?string $model = Sucursal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'sucursales';

    protected static ?string $navigationLabel = 'Sucursales';

    protected static ?string $modelLabel = 'sucursal';

    protected static ?string $pluralModelLabel = 'sucursales';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return SucursalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SucursalesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSucursales::route('/'),
            'create' => CreateSucursal::route('/nueva'),
            'edit' => EditSucursal::route('/{record}/editar'),
        ];
    }
}
