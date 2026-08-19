<?php

namespace App\Filament\Resources\Marcas;

use App\Filament\Resources\Marcas\Pages\ListMarcas;
use App\Filament\Resources\Marcas\RelationManagers\LineasRelationManager;
use App\Filament\Resources\Marcas\Schemas\MarcaForm;
use App\Filament\Resources\Marcas\Tables\MarcasTable;
use App\Models\Marca;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Catálogo compartido: las marcas sin empresa son las que mantiene Lotea y el
 * cliente solo puede verlas; las que él agrega sí las administra.
 */
class MarcaResource extends Resource
{
    protected static ?string $model = Marca::class;

    /**
     * Filament filtraría empresa_id = tenant a secas y escondería las marcas
     * del sistema. El filtrado correcto (globales + propias) lo hace el trait
     * EsCatalogoCompartido.
     */
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'marcas';

    protected static ?string $navigationLabel = 'Marcas y líneas';

    protected static ?string $modelLabel = 'marca';

    protected static ?string $pluralModelLabel = 'marcas y líneas';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return MarcaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarcasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [LineasRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarcas::route('/'),
            'edit' => Pages\EditMarca::route('/{record}/editar'),
        ];
    }
}
