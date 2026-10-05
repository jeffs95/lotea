<x-filament-panels::page>
    @php($totales = $this->getTotales())
    @php($filas = $this->getFilas())

    <div class="flex flex-wrap items-center gap-2">
        @foreach ($this->getPeriodos() as $clave => $etiqueta)
            <button
                type="button"
                wire:click="$set('periodo', '{{ $clave }}')"
                @class([
                    'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                    'bg-primary-600 text-white shadow-sm' => $periodo === $clave,
                    'bg-white text-gray-700 ring-1 ring-gray-950/5 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:ring-white/10 dark:hover:bg-gray-800' => $periodo !== $clave,
                ])
            >{{ $etiqueta }}</button>
        @endforeach
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Carros vendidos', $totales['carros'], null],
            ['Vendido', 'Q '.number_format((float) $totales['vendido'], 2), null],
            ['Comisiones', 'Q '.number_format((float) $totales['comisiones'], 2), 'Lo que hay que pagarles'],
            ['Vendedores activos', $totales['activos'].' de '.$filas->count(), 'Cerraron al menos un carro'],
        ] as [$titulo, $valor, $nota])
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $titulo }}</p>
                <p class="mt-1 text-2xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $valor }}</p>
                @if ($nota)
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $nota }}</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="fi-section overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3 font-medium">#</th>
                    <th class="px-4 py-3 font-medium">Vendedor</th>
                    <th class="px-4 py-3 text-right font-medium">Carros</th>
                    <th class="px-4 py-3 text-right font-medium">Vendido</th>
                    <th class="px-4 py-3 text-right font-medium">Comisión</th>
                    <th class="px-4 py-3 font-medium">Última venta</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @forelse ($filas as $i => $fila)
                    <tr @class(['bg-gray-50/50 dark:bg-white/[0.02]' => $fila['carros'] === 0])>
                        <td class="px-4 py-3 text-gray-400 dark:text-gray-500">{{ $i + 1 }}</td>

                        <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">
                            {{ $fila['vendedor']->name }}
                        </td>

                        <td class="px-4 py-3 text-right">
                            @if ($fila['carros'] > 0)
                                <span class="font-semibold text-gray-950 dark:text-white">{{ $fila['carros'] }}</span>
                            @else
                                <span class="text-gray-400 dark:text-gray-600">0</span>
                            @endif
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">
                            Q {{ number_format((float) $fila['vendido'], 2) }}
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">
                            Q {{ number_format((float) $fila['comisiones'], 2) }}
                        </td>

                        {{-- Lo que de verdad se viene a buscar: desde cuándo no vende. --}}
                        <td class="whitespace-nowrap px-4 py-3">
                            @if ($fila['ultima'] === null)
                                <span class="text-gray-400 dark:text-gray-600">Nunca</span>
                            @else
                                <span class="text-gray-700 dark:text-gray-300">{{ $fila['ultima']->format('d/m/Y') }}</span>
                                @if ($fila['dias_sin_vender'] >= 30)
                                    <x-filament::badge
                                        size="sm"
                                        :color="$fila['dias_sin_vender'] >= 90 ? 'danger' : 'warning'"
                                        class="ml-1 inline-flex"
                                    >hace {{ $fila['dias_sin_vender'] }} días</x-filament::badge>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                            Todavía no hay vendedores con el rol asignado ni ventas registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
