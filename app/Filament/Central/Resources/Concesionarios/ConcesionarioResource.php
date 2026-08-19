<?php

namespace App\Filament\Central\Resources\Concesionarios;

use App\Filament\Central\Concerns\EsRecursoDeLotea;
use App\Filament\Central\Resources\Concesionarios\Pages\CreateConcesionario;
use App\Filament\Central\Resources\Concesionarios\Pages\EditConcesionario;
use App\Filament\Central\Resources\Concesionarios\Pages\ListConcesionarios;
use App\Filament\Central\Resources\Concesionarios\Schemas\ConcesionarioForm;
use App\Filament\Central\Resources\Concesionarios\Tables\ConcesionariosTable;
use App\Models\Empresa;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConcesionarioResource extends Resource
{
    use EsRecursoDeLotea;

    protected static ?string $model = Empresa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'concesionarios';

    protected static ?string $navigationLabel = 'Concesionarios';

    protected static ?string $modelLabel = 'concesionario';

    protected static ?string $pluralModelLabel = 'concesionarios';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function getNavigationBadge(): ?string
    {
        return (string) Empresa::where('activa', true)->count();
    }

    public static function form(Schema $schema): Schema
    {
        return ConcesionarioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConcesionariosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConcesionarios::route('/'),
            'create' => CreateConcesionario::route('/nuevo'),
            'edit' => EditConcesionario::route('/{record}/editar'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['nombre', 'nombre_comercial', 'nit', 'slug'];
    }
}
