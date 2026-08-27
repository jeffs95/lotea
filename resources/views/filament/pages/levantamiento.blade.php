<x-filament-panels::page>
    {{-- La sucursal se elige una vez y queda fija durante todo el recorrido --}}
    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <label class="text-sm font-medium text-gray-950 dark:text-white">¿En qué patio estás?</label>

        <select
            wire:model.live="sucursalId"
            class="fi-input mt-1.5 block w-full rounded-lg border-none bg-white py-2.5 pe-8 ps-3 text-base text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
        >
            @foreach ($this->getSucursales() as $sucursal)
                <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
            @endforeach
        </select>

        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            Todo lo que captures queda en esta sucursal. Cambialo solo si te movés de patio.
        </p>
    </div>

    <form wire:submit="guardarYSeguir">
        {{ $this->form }}

        {{-- Botón grande y fijo abajo: se usa con una mano, de pie --}}
        <div class="sticky bottom-0 z-10 -mx-4 mt-4 border-t border-gray-200 bg-gray-50/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-xl sm:border dark:border-white/10 dark:bg-gray-900/95">
            <x-filament::button
                type="submit"
                size="lg"
                class="w-full justify-center"
                icon="heroicon-o-check-circle"
                wire:loading.attr="disabled"
                wire:target="guardarYSeguir"
            >
                {{-- Mientras no se guarda, el botón dice lo suyo --}}
                <span wire:loading.remove wire:target="guardarYSeguir">
                    Guardar y seguir con el siguiente
                </span>

                {{-- Y mientras sube, va contando. El texto lo escribe el
                     servidor foto por foto: sin eso, la pantalla se queda
                     quieta medio minuto y el vendedor vuelve a darle al botón,
                     que es como se duplica un carro. --}}
                <span wire:loading.flex wire:target="guardarYSeguir" class="items-center gap-2">
                    <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor"
                              d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" />
                    </svg>
                    <span wire:stream="avance">Guardando…</span>
                </span>
            </x-filament::button>

            {{-- Que no se pueda mandar dos veces mientras trabaja --}}
            <div wire:loading.delay.longer wire:target="guardarYSeguir"
                 class="mt-2 text-center text-xs text-gray-500 dark:text-gray-400">
                No cierre esta pantalla: las fotos se están subiendo.
            </div>

            @if (filled($capturadas))
                <p class="mt-2 text-center text-sm font-medium text-gray-600 dark:text-gray-300">
                    {{ count($capturadas) }} {{ count($capturadas) === 1 ? 'carro capturado' : 'carros capturados' }} en esta sesión
                </p>
            @endif
        </div>
    </form>

    @if (filled($capturadas))
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-white/5">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Capturados en esta sesión</h3>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach (array_reverse($capturadas) as $unidad)
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                {{ $unidad['stock_no'] }} · {{ $unidad['descripcion'] }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Q {{ number_format($unidad['precio'], 0) }}
                                @if (filled($unidad['falta']))
                                    · falta {{ implode(', ', $unidad['falta']) }}
                                @endif
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            @if ($unidad['publicada'])
                                <x-filament::badge color="success" size="sm">En el portal</x-filament::badge>
                            @else
                                <x-filament::badge color="warning" size="sm">Sin publicar</x-filament::badge>
                            @endif

                            <a href="{{ $unidad['url'] }}" target="_blank"
                               class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">
                                Abrir
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-panels::page>
