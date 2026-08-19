<?php

namespace App\Filament\Central\Resources\Planes;

use App\Filament\Central\Resources\Planes\Pages\CreatePlan;
use App\Filament\Central\Resources\Planes\Pages\EditPlan;
use App\Filament\Central\Resources\Planes\Pages\ListPlanes;
use App\Filament\Central\Resources\Planes\Schemas\PlanForm;
use App\Filament\Central\Resources\Planes\Tables\PlanesTable;
use App\Models\Plan;
use BackedEnum;
use App\Filament\Central\Concerns\EsRecursoDeLotea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    use EsRecursoDeLotea;

    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'planes';

    protected static ?string $navigationLabel = 'Planes';

    protected static ?string $modelLabel = 'plan';

    protected static ?string $pluralModelLabel = 'planes';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return PlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlanesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlanes::route('/'),
            'create' => CreatePlan::route('/nuevo'),
            'edit' => EditPlan::route('/{record}/editar'),
        ];
    }
}
