<?php

namespace App\Filament\Central\Resources\Cobros;

use App\Filament\Central\Resources\Cobros\Pages\ListCobros;
use App\Filament\Central\Resources\Cobros\Schemas\CobroForm;
use App\Filament\Central\Resources\Cobros\Tables\CobrosTable;
use App\Models\Cobro;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CobroResource extends Resource
{
    protected static ?string $model = Cobro::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'cobros';

    protected static ?string $navigationLabel = 'Cobros';

    protected static ?string $modelLabel = 'cobro';

    protected static ?string $pluralModelLabel = 'cobros';

    protected static ?string $recordTitleAttribute = 'concepto';

    /** Lo vencido en rojo: es la plata que ya debería estar en la cuenta. */
    public static function getNavigationBadge(): ?string
    {
        $vencidos = Cobro::porCobrar()->whereDate('vence_en', '<', now())->count();

        return $vencidos > 0 ? (string) $vencidos : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return CobroForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CobrosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCobros::route('/'),
            'create' => Pages\CreateCobro::route('/nuevo'),
            'edit' => Pages\EditCobro::route('/{record}/editar'),
        ];
    }
}
