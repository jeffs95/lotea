@php
    $costo = $this->getCostoTotal();
    $precio = $this->getPrecio();
    $gastosVenta = $this->getGastosDeVenta();
    $utilidad = $this->getUtilidad();
    $margen = $this->getMargen();
    $grupos = $this->getGrupos();
    $desviaciones = $this->getDesviaciones();
    $positiva = $utilidad >= 0;
    $q = fn ($n) => 'Q ' . number_format((float) $n, 2);
@endphp

<x-filament-panels::page>
    {{-- Los tres números que el dueño busca al abrir esta pantalla --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Costo total</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $q($costo) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Todo lo que se le metió al carro</p>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Precio de lista</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $q($precio) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ $record->dias_inventario !== null ? $record->dias_inventario . ' días en inventario' : 'Sin fecha de compra' }}
            </p>
        </div>

        <div @class([
            'rounded-xl p-5 shadow-sm ring-1',
            'bg-success-50 ring-success-600/20 dark:bg-success-500/10 dark:ring-success-400/30' => $positiva,
            'bg-danger-50 ring-danger-600/20 dark:bg-danger-500/10 dark:ring-danger-400/30' => ! $positiva,
        ])>
            <p @class([
                'text-sm font-medium',
                'text-success-700 dark:text-success-400' => $positiva,
                'text-danger-700 dark:text-danger-400' => ! $positiva,
            ])>Utilidad</p>
            <p @class([
                'mt-2 text-3xl font-bold tracking-tight',
                'text-success-700 dark:text-success-400' => $positiva,
                'text-danger-700 dark:text-danger-400' => ! $positiva,
            ])>{{ $q($utilidad) }}</p>
            <p @class([
                'mt-1 text-xs font-medium',
                'text-success-700/80 dark:text-success-400/80' => $positiva,
                'text-danger-700/80 dark:text-danger-400/80' => ! $positiva,
            ])>
                {{ $margen === null ? 'Falta el precio de lista' : number_format($margen, 1) . '% de margen' }}
            </p>
        </div>
    </div>

    {{-- Composición: en qué se fue la plata, de un vistazo --}}
    @if ($costo > 0)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="mb-3 text-sm font-medium text-gray-500 dark:text-gray-400">Composición del costo</p>

            @php
                $colores = [
                    'compra' => 'bg-primary-500',
                    'importacion' => 'bg-info-500',
                    'taller' => 'bg-warning-500',
                    'venta' => 'bg-gray-400',
                    'otros' => 'bg-gray-300',
                ];
            @endphp

            <div class="flex h-3 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                @foreach ($grupos->where('afectaCosto', true) as $g)
                    <div
                        class="{{ $colores[$g['grupo']] ?? 'bg-gray-400' }}"
                        style="width: {{ ($g['total'] / $costo) * 100 }}%"
                        title="{{ $g['etiqueta'] }}"
                    ></div>
                @endforeach
            </div>

            <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2">
                @foreach ($grupos->where('afectaCosto', true) as $g)
                    <span class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                        <span class="h-2.5 w-2.5 rounded-full {{ $colores[$g['grupo']] ?? 'bg-gray-400' }}"></span>
                        {{ $g['etiqueta'] }}
                        <span class="font-semibold text-gray-950 dark:text-white">
                            {{ number_format(($g['total'] / $costo) * 100, 1) }}%
                        </span>
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- El estado de resultados propiamente dicho --}}
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Estado de resultados de la unidad</h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Cada gasto en la moneda en que se pagó, convertido al tipo de cambio del documento.
                    </p>
                </div>

                @forelse ($grupos as $g)
                    <div class="border-b border-gray-100 dark:border-white/5">
                        <div class="flex items-center justify-between bg-gray-50 px-5 py-2 dark:bg-white/5">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ $g['etiqueta'] }}
                            </span>
                            @if ($g['afectaCosto'])
                                <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $q($g['total']) }}</span>
                            @else
                                <span class="text-xs text-gray-400">no suma al costo</span>
                            @endif
                        </div>

                        @foreach ($g['lineas'] as $linea)
                            <div class="flex items-baseline justify-between gap-4 px-5 py-2.5">
                                <div class="min-w-0">
                                    <p class="truncate text-sm text-gray-900 dark:text-gray-100">
                                        {{ $linea->categoria->nombre }}
                                        @if ($linea->vieneDeProrrateo())
                                            <span class="ml-1 rounded bg-info-50 px-1.5 py-0.5 text-[10px] font-medium text-info-700 dark:bg-info-500/10 dark:text-info-400">
                                                prorrateado
                                            </span>
                                        @endif
                                    </p>
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                        {{ collect([$linea->proveedor?->nombre, $linea->documento, $linea->descripcion])->filter()->implode(' · ') ?: $linea->fecha?->format('d/m/Y') }}
                                    </p>
                                </div>

                                <div class="shrink-0 text-right">
                                    @if ($linea->moneda !== 'GTQ')
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            $ {{ number_format((float) $linea->monto, 2) }} × {{ rtrim(rtrim(number_format((float) $linea->tipo_cambio, 4), '0'), '.') }}
                                        </p>
                                    @endif
                                    <p @class([
                                        'text-sm tabular-nums',
                                        'font-medium text-gray-950 dark:text-white' => $g['afectaCosto'],
                                        'text-gray-400 line-through' => ! $g['afectaCosto'],
                                    ])>{{ $q($linea->monto_base) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <div class="px-5 py-12 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Esta unidad todavía no tiene gastos registrados.</p>
                    </div>
                @endforelse

                {{-- El cierre: costo, precio, comisiones y utilidad --}}
                <div class="space-y-2 bg-gray-50 px-5 py-4 dark:bg-white/5">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-950 dark:text-white">Costo total</span>
                        <span class="text-sm font-bold tabular-nums text-gray-950 dark:text-white">{{ $q($costo) }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-300">Precio de lista</span>
                        <span class="text-sm tabular-nums text-gray-600 dark:text-gray-300">{{ $q($precio) }}</span>
                    </div>

                    @if ($gastosVenta > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-300">Gastos de venta</span>
                            <span class="text-sm tabular-nums text-gray-600 dark:text-gray-300">({{ $q($gastosVenta) }})</span>
                        </div>
                    @endif

                    <div class="flex items-center justify-between border-t border-gray-200 pt-2 dark:border-white/10">
                        <span class="text-base font-bold text-gray-950 dark:text-white">Utilidad bruta</span>
                        <span @class([
                            'text-base font-bold tabular-nums',
                            'text-success-600 dark:text-success-400' => $positiva,
                            'text-danger-600 dark:text-danger-400' => ! $positiva,
                        ])>
                            {{ $q($utilidad) }}
                            @if ($margen !== null)
                                <span class="text-xs font-medium">({{ number_format($margen, 1) }}%)</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Presupuestado vs real --}}
        <div>
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Presupuestado vs real</h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Dónde se pasó de lo que estimó antes de pujar.</p>
                </div>

                @forelse ($desviaciones as $d)
                    @php($sobre = $d['desviacion'] > 0)
                    <div class="border-b border-gray-100 px-5 py-3 last:border-0 dark:border-white/5">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="truncate text-sm text-gray-900 dark:text-gray-100">{{ $d['categoria']->nombre }}</span>
                            <span @class([
                                'shrink-0 text-sm font-semibold tabular-nums',
                                'text-danger-600 dark:text-danger-400' => $sobre,
                                'text-success-600 dark:text-success-400' => ! $sobre,
                            ])>
                                {{ $sobre ? '+' : '' }}{{ $q($d['desviacion']) }}
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            Estimado {{ $q($d['presupuesto']) }} · real {{ $q($d['real']) }}
                        </p>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Sin presupuesto cargado.</p>
                        <p class="mt-1 text-xs text-gray-400">
                            Registrá gastos marcados como presupuesto antes de comprar y aquí vas a ver la comparación.
                        </p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
