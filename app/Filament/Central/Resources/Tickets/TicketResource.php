<?php

namespace App\Filament\Central\Resources\Tickets;

use App\Filament\Central\Concerns\EsRecursoDeLotea;
use App\Filament\Central\Resources\Tickets\Pages\ListTickets;
use App\Filament\Central\Resources\Tickets\Tables\TicketsTable;
use App\Models\Ticket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * La bandeja de soporte de Lotea: lo que reportan todos los clientes.
 */
class TicketResource extends Resource
{
    use EsRecursoDeLotea;

    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'soporte';

    protected static ?string $navigationLabel = 'Soporte';

    protected static ?string $modelLabel = 'ticket';

    protected static ?string $pluralModelLabel = 'tickets';

    protected static ?string $recordTitleAttribute = 'asunto';

    public static function getNavigationBadge(): ?string
    {
        $pendientes = Ticket::pendientes()->count();

        return $pendientes > 0 ? (string) $pendientes : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return TicketsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
        ];
    }
}
