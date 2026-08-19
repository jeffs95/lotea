<x-filament-panels::page>
    @if ($this->puedeVerCostos)
        <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">Capital inmovilizado en el patio</p>
            <p class="mt-1 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">
                Q {{ number_format($this->getCapitalTotal(), 2) }}
            </p>
        </div>
    @endif

    {{-- Scroll horizontal: son 11 etapas y no caben en una pantalla. --}}
    <div class="overflow-x-auto pb-4">
        <div class="flex gap-4" style="min-width: max-content;">
            @foreach ($this->getColumnas() as $columna)
                @php($estado = $columna['estado'])
                <div class="w-72 shrink-0">
                    <div class="mb-3 rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10">
                        <div class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-1.5 text-sm font-semibold text-gray-950 dark:text-white">
                                <x-filament::icon :icon="$estado->getIcon()" class="h-4 w-4" />
                                {{ $estado->getLabel() }}
                            </span>
                            <x-filament::badge :color="$estado->getColor()">{{ $columna['total'] }}</x-filament::badge>
                        </div>

                        @if ($this->puedeVerCostos && $columna['capital'] > 0)
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Q {{ number_format($columna['capital'], 2) }} detenidos
                            </p>
                        @endif
                    </div>

                    <div class="space-y-2">
                        @forelse ($columna['unidades'] as $unidad)
                            @php($dias = $unidad->dias_en_estado)
                            <a
                                href="{{ \App\Filament\Resources\Unidades\UnidadResource::getUrl('edit', ['record' => $unidad]) }}"
                                class="block rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition hover:ring-primary-500 dark:bg-gray-900 dark:ring-white/10"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $unidad->stock_no }}</span>
                                    <x-filament::badge
                                        size="sm"
                                        :color="match (true) {
                                            $dias === null => 'gray',
                                            $dias > 30 => 'danger',
                                            $dias > 15 => 'warning',
                                            default => 'success',
                                        }"
                                    >
                                        {{ $dias === null ? '—' : $dias . 'd' }}
                                    </x-filament::badge>
                                </div>

                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $unidad->descripcion }}</p>

                                @if ($this->puedeVerCostos && $unidad->costo_total > 0)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Q {{ number_format((float) $unidad->costo_total, 2) }}
                                    </p>
                                @endif
                            </a>
                        @empty
                            <p class="rounded-lg border border-dashed border-gray-300 px-3 py-4 text-center text-xs text-gray-400 dark:border-gray-700">
                                Vacío
                            </p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
