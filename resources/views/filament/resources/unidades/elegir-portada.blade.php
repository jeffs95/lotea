{{--
    Las fotos ya guardadas, para elegir con cuál se presenta el carro.

    Va aparte del subidor y no dentro: ahí las fotos son archivos en tránsito
    —algunas todavía subiendo— y aquí son las que ya están, que es sobre las que
    tiene sentido decidir.
--}}
@php
    $fotos = $getRecord()?->getMedia('fotos') ?? collect();
@endphp

{{-- Con el envoltorio de Filament, que es el que pinta la etiqueta y la ata
     al contenido para quien usa lector de pantalla. --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
@if ($fotos->count() > 1)
    <div class="space-y-3">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Tocá una para que sea la portada. Es la única que ve quien recorre el catálogo.
        </p>

        <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
            @foreach ($fotos as $i => $foto)
                @php($esPortada = $i === 0)

                <button
                    type="button"
                    wire:click="hacerPortada({{ $foto->getKey() }})"
                    wire:loading.attr="disabled"
                    @disabled($esPortada)
                    @class([
                        'group relative aspect-[4/3] overflow-hidden rounded-lg ring-2 transition',
                        'ring-primary-500 cursor-default' => $esPortada,
                        'ring-transparent hover:ring-gray-300 dark:hover:ring-white/30' => ! $esPortada,
                    ])
                    title="{{ $esPortada ? 'Es la portada' : 'Hacer que esta sea la portada' }}"
                >
                    <img src="{{ $foto->getUrl('miniatura') ?: $foto->getUrl() }}"
                         alt="" loading="lazy"
                         class="h-full w-full object-cover">

                    @if ($esPortada)
                        <span class="bg-primary-600 absolute inset-x-0 bottom-0 py-1 text-center text-[0.65rem] font-bold uppercase tracking-wide text-white">
                            Portada
                        </span>
                    @else
                        {{-- El aviso solo al pasar por encima: con un rótulo en
                             cada foto, la cuadrícula se vuelve ilegible. --}}
                        <span class="absolute inset-0 flex items-center justify-center bg-gray-900/0 text-xs font-semibold text-white/0 transition group-hover:bg-gray-900/50 group-hover:text-white">
                            Hacer portada
                        </span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>
@elseif ($fotos->count() === 1)
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Con una sola foto, esa es la portada. Subí más y aquí podrás elegir.
    </p>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Todavía no hay fotos. La primera que subas será la portada.
    </p>
@endif
</x-dynamic-component>
