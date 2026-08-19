<?php

namespace App\Filament\Resources\Leads;

use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Leads\Schemas\LeadForm;
use App\Filament\Resources\Leads\Tables\LeadsTable;
use App\Models\Lead;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'leads';

    protected static ?string $navigationLabel = 'Prospectos';

    protected static ?string $modelLabel = 'prospecto';

    protected static ?string $pluralModelLabel = 'prospectos';

    protected static ?string $recordTitleAttribute = 'nombre';

    /** El contador del menú son los que nadie ha atendido todavía. */
    public static function getNavigationBadge(): ?string
    {
        $sinAtender = Lead::whereNull('primera_respuesta_en')->count();

        return $sinAtender > 0 ? (string) $sinAtender : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return LeadForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeadsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/nuevo'),
            'edit' => Pages\EditLead::route('/{record}/editar'),
        ];
    }
}
