@php($unidades = $this->getUnidades())

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

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 print:grid-cols-3 print:gap-3">
        @foreach ($unidades as $unidad)
            <div class="break-inside-avoid rounded-xl border border-gray-300 bg-white p-4 text-center print:border-gray-400">
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Stock {{ $unidad->stock_no }}</p>

                <p class="mt-1 text-base font-bold leading-tight text-gray-900">{{ $unidad->descripcion }}</p>

                <img src="{{ $this->qr($unidad) }}" alt="Código {{ $unidad->codigo_qr }}" class="mx-auto mt-3 h-40 w-40">

                <p class="mt-2 font-mono text-lg font-bold tracking-[0.2em] text-gray-900">{{ $unidad->codigo_qr }}</p>

                <p class="mt-1 text-xs text-gray-500">Escaneá para ver fotos, ficha y precio</p>
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
        }
    </style>
</x-filament-panels::page>
