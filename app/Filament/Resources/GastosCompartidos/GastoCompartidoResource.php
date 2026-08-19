<?php

namespace App\Filament\Resources\GastosCompartidos;

use App\Filament\Resources\GastosCompartidos\Pages\CreateGastoCompartido;
use App\Filament\Resources\GastosCompartidos\Pages\ListGastosCompartidos;
use App\Filament\Resources\GastosCompartidos\Schemas\GastoCompartidoForm;
use App\Filament\Resources\GastosCompartidos\Tables\GastosCompartidosTable;
use App\Models\GastoCompartido;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Gastos que cubren varias unidades: el flete del contenedor, los honorarios
 * de una póliza con seis carros.
 *
 * No se editan: si el reparto quedó mal, se anula y se registra de nuevo. Un
 * gasto ya prorrateado tocó el costo de varias unidades, y cambiarlo en
 * silencio movería márgenes que alguien ya usó para decidir un precio.
 */
class GastoCompartidoResource extends Resource
{
    protected static ?string $model = GastoCompartido::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'gastos-compartidos';

    protected static ?string $navigationLabel = 'Gastos compartidos';

    protected static ?string $modelLabel = 'gasto compartido';

    protected static ?string $pluralModelLabel = 'gastos compartidos';

    protected static ?string $recordTitleAttribute = 'descripcion';

    public static function form(Schema $schema): Schema
    {
        return GastoCompartidoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GastosCompartidosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGastosCompartidos::route('/'),
            'create' => CreateGastoCompartido::route('/nuevo'),
        ];
    }
}
