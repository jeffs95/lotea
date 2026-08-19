<?php

namespace App\Filament\Resources\Usuarios;

use App\Filament\Resources\Usuarios\Pages\CreateUsuario;
use App\Filament\Resources\Usuarios\Pages\EditUsuario;
use App\Filament\Resources\Usuarios\Pages\ListUsuarios;
use App\Filament\Resources\Usuarios\Schemas\UsuarioForm;
use App\Filament\Resources\Usuarios\Tables\UsuariosTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Los usuarios no llevan empresa_id: cuelgan del pivote empresa_user, así que
 * el aislamiento aquí lo hace Filament a través de la relación del tenant y no
 * el EmpresaScope.
 */
class UsuarioResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'usuarios';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    protected static ?string $recordTitleAttribute = 'name';

    /** Empresa::usuarios() — por aquí crea y lista Filament. */
    protected static ?string $tenantRelationshipName = 'usuarios';

    /** User::empresas() — por aquí filtra. Es belongsToMany, no belongsTo. */
    protected static ?string $tenantOwnershipRelationshipName = 'empresas';

    public static function form(Schema $schema): Schema
    {
        return UsuarioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsuariosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsuarios::route('/'),
            'create' => CreateUsuario::route('/nuevo'),
            'edit' => EditUsuario::route('/{record}/editar'),
        ];
    }
}
