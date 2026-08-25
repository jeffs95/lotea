@php($unidades = $this->getUnidades())
@php($empresa = \Filament\Facades\Filament::getTenant())

<x-filament-panels::page>
    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $unidades->count() }} {{ $unidades->count() === 1 ? 'etiqueta' : 'etiquetas' }}.
            Imprimí en papel adhesivo y pegá cada una en su parabrisas.
        </p>

        <x-filament::button icon="heroicon-o-printer" x-data x-on:click="window.print()">
            Imprimir
        </x-filament::button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 print:grid-cols-3 print:gap-2">
        @foreach ($unidades as $unidad)
            {{-- Cada etiqueta se recorta entera: nunca partida entre dos hojas --}}
            <div class="etiqueta break-inside-avoid overflow-hidden rounded-2xl bg-white ring-1 ring-gray-300">

                {{-- La marca del concesionario arriba, en su color --}}
                <div class="flex items-center justify-center px-4 py-2.5"
                     style="background: {{ $empresa?->color_de_marca ?? '#111827' }}">
                    @if ($empresa?->logo_url)
                        <img src="{{ $empresa->logo_url }}" alt="{{ $empresa->getFilamentName() }}"
                             class="h-6 w-auto object-contain">
                    @else
                        <span class="text-sm font-bold uppercase tracking-widest text-white">
                            {{ $empresa?->getFilamentName() }}
                        </span>
                    @endif
                </div>

                <div class="px-4 pb-4 pt-3 text-center">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-gray-400">
                        Stock {{ $unidad->stock_no }}
                    </p>

                    <p class="mt-0.5 line-clamp-2 text-sm font-bold leading-tight text-gray-900">
                        {{ $unidad->descripcion }}
                    </p>

                    <img src="{{ $this->qr($unidad) }}" alt="Código {{ $unidad->codigo_qr }}"
                         class="mx-auto mt-3 h-36 w-36">

                    <p class="mt-2.5 font-mono text-base font-bold tracking-[0.18em] text-gray-900">
                        {{ $unidad->codigo_qr }}
                    </p>

                    <div class="mt-2 flex items-center justify-center gap-1.5 border-t border-dashed border-gray-200 pt-2">
                        <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z" />
                        </svg>
                        <p class="text-[0.68rem] leading-tight text-gray-500">
                            Escaneá con la cámara: fotos, ficha y precio
                        </p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($unidades->isEmpty())
        <p class="rounded-xl border border-dashed border-gray-300 px-6 py-12 text-center text-gray-500 dark:border-gray-700">
            No hay unidades para etiquetar.
        </p>
    @endif

    {{-- Que la hoja salga limpia: sin menú, sin encabezados, solo etiquetas. --}}
    <style>
        @media print {
            .fi-topbar, .fi-sidebar, .fi-header, .fi-breadcrumbs { display: none !important; }
            .fi-main, .fi-page { padding: 0 !important; margin: 0 !important; max-width: none !important; }
            body { background: #fff !important; }

            /* Los fondos de color no se imprimen si no se pide expresamente, y
               la cabecera de la etiqueta saldría en blanco. */
            .etiqueta { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</x-filament-panels::page>
