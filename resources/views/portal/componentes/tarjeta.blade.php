@props(['unidad'])

@php
    $foto = $unidad->getFirstMediaUrl('fotos', 'web') ?: null;
    $enCamino = ! in_array($unidad->estado->etapa(), ['venta'], true);
@endphp

<a href="{{ \App\Support\PortalUrl::unidad($empresa, $unidad->slug) }}"
   class="group flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 transition hover:shadow-lg hover:ring-gray-300">

    <div class="relative aspect-[4/3] overflow-hidden bg-gray-100">
        @if ($foto)
            <img src="{{ $foto }}" alt="{{ $unidad->descripcion }}" loading="lazy"
                 class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
        @else
            {{-- Sin fotos todavía: mejor un marcador con identidad que una caja gris --}}
            <div class="flex h-full w-full flex-col items-center justify-center gap-2 bg-gradient-to-br from-gray-100 to-gray-200">
                <svg class="h-12 w-12 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-8.25m0-11.25h5.379a2.25 2.25 0 011.59.659l2.253 2.252m-9.222 8.339H2.25V6.375c0-.621.504-1.125 1.125-1.125H9.75" />
                </svg>
                <span class="text-xs font-medium text-gray-400">Fotos en camino</span>
            </div>
        @endif

        @if ($enCamino)
            <span class="absolute left-3 top-3 rounded-full bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white shadow">
                Próximamente
            </span>
        @endif

        @if ($unidad->destacado)
            <span class="absolute right-3 top-3 rounded-full px-2.5 py-1 text-xs font-semibold text-white shadow" style="background: var(--acento)">
                Destacado
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col p-4">
        <p class="text-xs font-medium text-gray-400">Stock {{ $unidad->stock_no }}</p>
        <h3 class="mt-0.5 text-base font-bold leading-snug text-gray-900">{{ $unidad->descripcion }}</h3>

        <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500">
            @if ($unidad->odometro)
                <span>{{ number_format($unidad->odometro) }} {{ $unidad->odometro_unidad === 'mi' ? 'mi' : 'km' }}</span>
            @endif
            @if ($unidad->cilindrada_cc)<span>· {{ $unidad->cilindrada_cc }} cc</span>@endif
            @if ($unidad->transmision)<span>· {{ $unidad->tipo_vehiculo->transmisiones()[$unidad->transmision] ?? $unidad->transmision }}</span>@endif
            @if ($unidad->combustible)<span>· {{ \App\Filament\Resources\Unidades\Schemas\UnidadForm::COMBUSTIBLES[$unidad->combustible] ?? $unidad->combustible }}</span>@endif
        </div>

        <div class="mt-auto flex items-end justify-between pt-4">
            <p class="text-xl font-bold text-gray-900">
                Q {{ number_format((float) $unidad->precio_lista, 0) }}
            </p>
            @if ($unidad->sucursal)
                <span class="text-xs text-gray-400">{{ $unidad->sucursal->nombre }}</span>
            @endif
        </div>
    </div>
</a>
