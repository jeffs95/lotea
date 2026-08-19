<?php

namespace App\Filament\Central\Pages;

use App\Models\Empresa;
use App\Models\User;
use App\Support\CatalogoDePermisos;
use App\Support\Tenancy;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * "No puedo agregar un vehículo".
 *
 * Nueve de cada diez veces es un permiso que le falta al rol, y se resuelve
 * mirando esta pantalla sin entrar a la cuenta del cliente ni ver un solo dato
 * suyo.
 */
class DiagnosticoDePermisos extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'diagnostico';

    protected static ?string $navigationLabel = 'Diagnóstico';

    protected static ?string $title = '¿Qué puede hacer este usuario?';

    protected string $view = 'filament.central.pages.diagnostico-de-permisos';

    public ?int $empresaId = null;

    public ?int $usuarioId = null;

    /** Acepta ?empresaId=&usuarioId= para llegar directo desde un ticket. */
    public function mount(): void
    {
        $this->empresaId = request()->integer('empresaId') ?: Empresa::orderBy('nombre')->value('id');
        $this->usuarioId = request()->integer('usuarioId') ?: null;
    }

    public function updatedEmpresaId(): void
    {
        $this->usuarioId = null;
    }

    /** @return Collection<int, Empresa> */
    public function getEmpresas(): Collection
    {
        return Empresa::orderBy('nombre')->get();
    }

    public function getEmpresa(): ?Empresa
    {
        return $this->empresaId ? Empresa::find($this->empresaId) : null;
    }

    /** @return Collection<int, User> */
    public function getUsuarios(): Collection
    {
        return $this->getEmpresa()?->usuarios()->orderBy('name')->get() ?? collect();
    }

    public function getUsuario(): ?User
    {
        return $this->usuarioId ? $this->getUsuarios()->firstWhere('id', $this->usuarioId) : null;
    }

    /** @return Collection<int, string> */
    public function getRoles(): Collection
    {
        return $this->conContextoDelCliente(fn (User $usuario) => $usuario->getRoleNames()) ?? collect();
    }

    /** El cuadro completo: cada módulo con lo que puede y lo que no. */
    public function getMatriz(): Collection
    {
        $concedidos = $this->conContextoDelCliente(
            fn (User $usuario) => $usuario->getAllPermissions()->pluck('name'),
        );

        if ($concedidos === null) {
            return collect();
        }

        return CatalogoDePermisos::agrupar(Permission::pluck('name'), $concedidos);
    }

    /**
     * Para pegarle la respuesta al dueño por WhatsApp sin escribirla a mano.
     *
     * Lista lo que SÍ puede: enumerar lo que no puede da cuarenta líneas que
     * nadie lee en un chat.
     */
    public function getResumenParaCopiar(): string
    {
        $usuario = $this->getUsuario();

        if (! $usuario) {
            return '';
        }

        $matriz = $this->getMatriz();

        // Si tiene todas las acciones de un módulo se dice "todo": enumerar
        // las doce por módulo convierte el mensaje en un muro ilegible.
        $conAcceso = $matriz
            ->map(function (array $acciones) {
                $concedidas = collect($acciones)->where('concedido', true)->pluck('accion');

                return $concedidas->count() === count($acciones) && $concedidas->count() > 1
                    ? collect(['todo'])
                    : $concedidas;
            })
            ->reject(fn (Collection $acciones) => $acciones->isEmpty());

        $sinAcceso = $matriz
            ->reject(fn (array $acciones, string $modulo) => $conAcceso->has($modulo))
            ->keys();

        $lineas = collect([
            "Usuario: {$usuario->name} ({$usuario->email})",
            'Empresa: '.($this->getEmpresa()->nombre_comercial ?: $this->getEmpresa()->nombre),
            'Rol: '.($this->getRoles()->implode(', ') ?: 'SIN ROL ASIGNADO'),
            '',
        ]);

        if ($conAcceso->isEmpty()) {
            return $lineas->push('No puede hacer nada en el sistema.')->implode("\n");
        }

        $lineas->push('Puede:');
        $conAcceso->each(fn (Collection $acciones, string $modulo) => $lineas->push("· {$modulo}: ".$acciones->implode(', ')));

        if ($sinAcceso->isNotEmpty()) {
            $lineas->push('');
            $lineas->push('Sin acceso a: '.$sinAcceso->implode(', '));
        }

        return $lineas->implode("\n");
    }

    /**
     * Los roles de spatie viven por empresa: para leer los de este usuario hay
     * que preguntarlos parados en la suya, no en el vacío del panel central.
     */
    protected function conContextoDelCliente(callable $consulta): mixed
    {
        $usuario = $this->getUsuario();
        $empresa = $this->getEmpresa();

        if (! $usuario || ! $empresa) {
            return null;
        }

        return Tenancy::comoEmpresa($empresa, function () use ($consulta, $usuario) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $consulta($usuario->fresh());
        });
    }
}
