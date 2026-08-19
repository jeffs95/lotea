<?php

namespace App\Filament\Resources\CategoriasCosto;

use App\Filament\Resources\CategoriasCosto\Pages\CreateCategoriaCosto;
use App\Filament\Resources\CategoriasCosto\Pages\EditCategoriaCosto;
use App\Filament\Resources\CategoriasCosto\Pages\ListCategoriasCosto;
use App\Filament\Resources\CategoriasCosto\Schemas\CategoriaCostoForm;
use App\Filament\Resources\CategoriasCosto\Tables\CategoriasCostoTable;
use App\Models\CategoriaCosto;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CategoriaCostoResource extends Resource
{
    protected static ?string $model = CategoriaCosto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'categorias-costo';

    protected static ?string $navigationLabel = 'Categorías de costo';

    protected static ?string $modelLabel = 'categoría de costo';

    protected static ?string $pluralModelLabel = 'categorías de costo';

    protected static ?string $recordTitleAttribute = 'nombre';

    public const GRUPOS = [
        'compra' => 'Compra',
        'importacion' => 'Importación',
        'taller' => 'Taller',
        'venta' => 'Venta',
        'otros' => 'Otros',
    ];

    public static function form(Schema $schema): Schema
    {
        return CategoriaCostoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoriasCostoTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategoriasCosto::route('/'),
            'create' => CreateCategoriaCosto::route('/nueva'),
            'edit' => EditCategoriaCosto::route('/{record}/editar'),
        ];
    }
}
