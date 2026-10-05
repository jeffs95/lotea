<?php

namespace App\Filament\Resources\Ventas;

use App\Filament\Concerns\SujetoAlPlan;
use App\Filament\Resources\Ventas\Pages\CreateVenta;
use App\Filament\Resources\Ventas\Pages\EditVenta;
use App\Filament\Resources\Ventas\Pages\ListVentas;
use App\Filament\Resources\Ventas\Schemas\VentaForm;
use App\Filament\Resources\Ventas\Tables\VentasTable;
use App\Models\Venta;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VentaResource extends Resource
{
    use SujetoAlPlan;

    protected static ?string $model = Venta::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'ventas';

    protected static ?string $navigationLabel = 'Ventas';

    protected static ?string $modelLabel = 'venta';

    protected static ?string $pluralModelLabel = 'ventas';

    protected static ?string $recordTitleAttribute = 'numero';

    public static function form(Schema $schema): Schema
    {
        return VentaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VentasTable::configure($table);
    }

    /**
     * El vendedor externo ve su propio trabajo, no el del resto del patio.
     *
     * Va aquí y no solo en la tabla porque de esta consulta cuelga todo lo
     * demás del recurso: el buscador global, el contador del menú y la
     * resolución del registro al abrir una URL.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visiblesPara(auth()->user());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVentas::route('/'),
            'create' => CreateVenta::route('/nueva'),
            'edit' => EditVenta::route('/{record}/editar'),
        ];
    }
}
