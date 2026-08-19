<?php

namespace App\Filament\Resources\Proveedores;

use App\Filament\Resources\Proveedores\Pages\CreateProveedor;
use App\Filament\Resources\Proveedores\Pages\EditProveedor;
use App\Filament\Resources\Proveedores\Pages\ListProveedores;
use App\Filament\Resources\Proveedores\Schemas\ProveedorForm;
use App\Filament\Resources\Proveedores\Tables\ProveedoresTable;
use App\Models\Proveedor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProveedorResource extends Resource
{
    protected static ?string $model = Proveedor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'proveedores';

    protected static ?string $navigationLabel = 'Proveedores';

    protected static ?string $modelLabel = 'proveedor';

    protected static ?string $pluralModelLabel = 'proveedores';

    protected static ?string $recordTitleAttribute = 'nombre';

    /** A quién se le paga. El tipo define en qué etapa aparece sugerido. */
    public const TIPOS = [
        'subasta' => 'Subasta',
        'naviera' => 'Naviera',
        'agente_aduanal' => 'Agente aduanal',
        'transporte' => 'Transporte',
        'taller' => 'Taller',
        'repuestos' => 'Repuestos',
        'otro' => 'Otro',
    ];

    public static function form(Schema $schema): Schema
    {
        return ProveedorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProveedoresTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProveedores::route('/'),
            'create' => CreateProveedor::route('/nuevo'),
            'edit' => EditProveedor::route('/{record}/editar'),
        ];
    }
}
