@php
    $empresa = $this->getEmpresa();
    $usuario = $this->getUsuario();
    $roles = $this->getRoles();
    $matriz = $this->getMatriz();
    $resumen = $this->getResumenParaCopiar();
@endphp

<x-filament-panels::page>
    {{-- Elegir a quién estamos diagnosticando --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="text-sm font-medium text-gray-950 dark:text-white">Concesionario</label>
            <select
                wire:model.live="empresaId"
                class="fi-input mt-1.5 block w-full rounded-lg border-none bg-white py-2 pe-8 ps-3 text-base text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20 sm:text-sm"
            >
                @foreach ($this->getEmpresas() as $opcion)
                    <option value="{{ $opcion->id }}">{{ $opcion->nombre_comercial ?: $opcion->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-sm font-medium text-gray-950 dark:text-white">Usuario</label>
            <select
                wire:model.live="usuarioId"
                class="fi-input mt-1.5 block w-full rounded-lg border-none bg-white py-2 pe-8 ps-3 text-base text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20 sm:text-sm"
            >
                <option value="">Elegí un usuario…</option>
                @foreach ($this->getUsuarios() as $opcion)
                    <option value="{{ $opcion->id }}">{{ $opcion->name }} · {{ $opcion->email }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if (! $usuario)
        <div class="rounded-xl border border-dashed border-gray-300 px-6 py-16 text-center dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Elegí un usuario para ver exactamente qué puede y qué no puede hacer.
            </p>
        </div>
    @else
        {{-- Quién es --}}
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div>
                <p class="text-lg font-bold text-gray-950 dark:text-white">{{ $usuario->name }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $usuario->email }} · {{ $empresa->nombre_comercial ?: $empresa->nombre }}
                </p>

                <div class="mt-2 flex flex-wrap items-center gap-2">
                    @forelse ($roles as $rol)
                        <x-filament::badge color="primary">{{ \Illuminate\Support\Str::headline($rol) }}</x-filament::badge>
                    @empty
                        <x-filament::badge color="danger">Sin rol asignado</x-filament::badge>
                    @endforelse

                    @unless ($usuario->activo)
                        <x-filament::badge color="danger">Usuario desactivado</x-filament::badge>
                    @endunless
                </div>
            </div>

            <div class="flex flex-col items-end gap-2">
                {{-- El texto se calcula en PHP: @js dentro de un atributo de
                     Alpine no lo compila Blade. --}}
                <x-filament::button
                    x-data="{ copiado: false }"
                    x-on:click="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($resumen) }}); copiado = true; setTimeout(() => copiado = false, 1500)"
                    icon="heroicon-o-clipboard-document"
                    color="gray"
                >
                    <span x-text="copiado ? 'Copiado' : 'Copiar diagnóstico'">Copiar diagnóstico</span>
                </x-filament::button>
                <span class="text-xs text-gray-400">Para pegárselo al dueño por WhatsApp</span>
            </div>
        </div>

        @if ($roles->isEmpty())
            <div class="rounded-xl bg-danger-50 p-4 ring-1 ring-danger-600/20 dark:bg-danger-500/10 dark:ring-danger-400/30">
                <p class="text-sm font-semibold text-danger-700 dark:text-danger-400">Este usuario no tiene ningún rol</p>
                <p class="mt-1 text-sm text-danger-700/80 dark:text-danger-400/80">
                    Por eso no puede hacer nada. El dueño se lo asigna desde Configuración → Usuarios, en su propio panel.
                </p>
            </div>
        @endif

        {{-- Lo que puede y lo que no, módulo por módulo --}}
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($matriz as $modulo => $acciones)
                @php($permitidas = collect($acciones)->where('concedido', true)->count())
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-white/5">
                        <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $modulo }}</span>
                        <span @class([
                            'text-xs font-medium',
                            'text-success-600 dark:text-success-400' => $permitidas === count($acciones),
                            'text-gray-400' => $permitidas === 0,
                            'text-warning-600 dark:text-warning-400' => $permitidas > 0 && $permitidas < count($acciones),
                        ])>{{ $permitidas }} de {{ count($acciones) }}</span>
                    </div>

                    <div class="divide-y divide-gray-50 dark:divide-white/5">
                        @foreach ($acciones as $accion)
                            <div class="flex items-center justify-between px-4 py-2">
                                <span @class([
                                    'text-sm',
                                    'text-gray-900 dark:text-gray-100' => $accion['concedido'],
                                    'text-gray-400 dark:text-gray-500' => ! $accion['concedido'],
                                ])>{{ $accion['accion'] }}</span>

                                @if ($accion['concedido'])
                                    <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 text-success-500" />
                                @else
                                    <x-filament::icon icon="heroicon-o-x-circle" class="h-5 w-5 text-gray-300 dark:text-gray-600" />
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
