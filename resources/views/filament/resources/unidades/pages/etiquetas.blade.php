@php($unidades = $this->getUnidades())
@php($empresa = \Filament\Facades\Filament::getTenant())

{{-- Resueltos una vez y no por etiqueta: el logo se pregunta al disco, que en
     producción es un FTP, y cuarenta etiquetas eran cuarenta viajes de ida. --}}
@php($colorDeCabecera = $empresa?->color_de_marca ?? '#111827')
@php($logoDeCabecera = $empresa?->logoParaFondo())
@php($nombreDelCliente = $empresa?->getFilamentName())

<x-filament-panels::page>
    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $unidades->count() }} {{ $unidades->count() === 1 ? 'etiqueta' : 'etiquetas' }}.
            Imprimí en papel adhesivo y pegá cada una en su parabrisas.
        </p>

        <x-filament::button
            icon="heroicon-o-printer"
            x-data="{
                async imprimir() {
                    /*
                     * El logo del concesionario viaja por red y el diálogo de
                     * impresión no espera a nadie: si se abre antes de que
                     * llegue, la cabecera de cada etiqueta sale vacía.
                     */
                    const enCamino = [...document.images].filter((img) => ! img.complete);

                    await Promise.all(enCamino.map((img) => new Promise((listo) => {
                        img.addEventListener('load', listo, { once: true });
                        img.addEventListener('error', listo, { once: true });
                    })));

                    window.print();
                },
            }"
            x-on:click="imprimir()"
        >
            Imprimir
        </x-filament::button>
    </div>

    <div class="hoja-de-etiquetas grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($unidades as $unidad)
            {{-- Cada etiqueta se recorta entera: nunca partida entre dos hojas --}}
            <div class="etiqueta break-inside-avoid overflow-hidden rounded-2xl bg-white ring-1 ring-gray-300">

                {{-- La marca del concesionario arriba, en su color --}}
                <div class="flex items-center justify-center px-4 py-2.5"
                     style="background: {{ $colorDeCabecera }}">
                    @if ($logoDeCabecera)
                        {{-- Sobre blanco y no directo sobre la banda: el logo del
                             cliente puede llevar su mismo color de marca dentro y
                             esa parte desaparecería. Pasó: se leía «RTADORA». --}}
                        <span class="rounded bg-white px-2 py-1">
                            <img src="{{ $logoDeCabecera }}" alt="{{ $nombreDelCliente }}"
                                 class="h-6 w-auto object-contain">
                        </span>
                    @else
                        <span class="text-sm font-bold uppercase tracking-widest text-white">
                            {{ $nombreDelCliente }}
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

                    {!! $this->qr($unidad) !!}

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

</x-filament-panels::page>
