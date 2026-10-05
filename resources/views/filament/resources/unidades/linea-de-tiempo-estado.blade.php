{{--
    El camino del carro, para tocar la etapa en la que está.

    Antes era una lista desplegable con los dos o tres destinos que la máquina
    de estados permitía, y eso escondía dos cosas: en qué punto del recorrido
    va la unidad, y que existen etapas más allá de las ofrecidas. Un patio que
    recién empieza a usar el sistema tiene carros ya listos en el parqueo, y no
    va a registrarles las diez etapas de importación del año pasado.

    La línea se dibuja por tramos entre un paso y el siguiente, y no como una
    barra de fondo: así queda siempre pegada a los círculos, midan lo que midan
    las etiquetas y se desplace como se desplace el carrusel horizontal.
--}}
@php
    $unidad = $getRecord();
    $actual = $unidad?->estado;
    $puestoActual = $actual?->puesto();
    $recorrido = \App\Enums\EstadoUnidad::recorrido();
    $baja = \App\Enums\EstadoUnidad::Baja;
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div x-data="{ elegido: $wire.$entangle('{{ $getStatePath() }}') }" class="space-y-3">

        {{-- Scroll horizontal: son quince etapas y no caben en un portátil. --}}
        <div class="-mx-1 overflow-x-auto px-1 pb-2">
            <div class="flex" style="min-width: max-content">
                @foreach ($recorrido as $i => $estado)
                    @php($andado = $puestoActual !== null && $i <= $puestoActual)
                    @php($tramoAndado = $puestoActual !== null && $i < $puestoActual)
                    @php($esActual = $estado === $actual)

                    <div class="relative flex w-[5.5rem] shrink-0 flex-col items-center">
                        {{-- Los dos medios tramos que salen de este círculo. --}}
                        @unless ($loop->first)
                            <span @class(['absolute left-0 top-[14px] h-1 w-1/2',
                                'bg-primary-600' => $andado,
                                'bg-gray-200 dark:bg-gray-700' => ! $andado])></span>
                        @endunless

                        @unless ($loop->last)
                            <span @class(['absolute right-0 top-[14px] h-1 w-1/2',
                                'bg-primary-600' => $tramoAndado,
                                'bg-gray-200 dark:bg-gray-700' => ! $tramoAndado])></span>
                        @endunless

                        <button
                            type="button"
                            x-on:click="elegido = @js($estado->value)"
                            @disabled($esActual)
                            class="group relative z-10 flex w-full flex-col items-center gap-1 disabled:cursor-default"
                        >
                            <span
                                @class([
                                    'flex h-8 w-8 items-center justify-center rounded-full border-2 bg-white transition dark:bg-gray-900',
                                    'border-primary-600' => $andado,
                                    'border-gray-300 dark:border-gray-600' => ! $andado,
                                    'group-hover:border-primary-500' => ! $esActual,
                                ])
                                x-bind:class="elegido === @js($estado->value) ? 'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-gray-900' : ''"
                            >
                                <x-filament::icon :icon="$estado->getIcon()"
                                    @class(['h-4 w-4', 'text-primary-600' => $andado, 'text-gray-400' => ! $andado]) />
                            </span>

                            <span
                                @class([
                                    'w-full px-0.5 text-center text-[11px] leading-tight',
                                    'font-semibold text-gray-950 dark:text-white' => $esActual,
                                    'text-gray-600 dark:text-gray-400' => ! $esActual,
                                ])
                            >{{ $estado->getLabel() }}</span>

                            <span @class(['text-[10px] font-semibold uppercase tracking-wide text-primary-600',
                                'invisible' => ! $esActual])>hoy</span>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- La baja va aparte y no al final de la línea: no es la meta del
             camino, es salirse de él. --}}
        <div class="flex items-center justify-between gap-3 border-t border-gray-200 pt-3 dark:border-gray-700">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Tocá la etapa en la que está el carro. Se puede saltar: queda anotado de dónde venía.
            </p>

            @if ($actual !== $baja)
                <button
                    type="button"
                    x-on:click="elegido = @js($baja->value)"
                    class="shrink-0 rounded-lg px-2.5 py-1 text-xs font-medium text-danger-600 transition hover:bg-danger-50 dark:hover:bg-danger-500/10"
                    x-bind:class="elegido === @js($baja->value) ? 'bg-danger-50 ring-1 ring-danger-500 dark:bg-danger-500/10' : ''"
                >Dar de baja</button>
            @endif
        </div>
    </div>
</x-dynamic-component>
