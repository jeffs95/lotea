{{--
    La cinta de ofertas, debajo del menú.

    Dice lo mismo que el aviso de entrada, pero para quien ya lo cerró o entró
    directo a una ficha. Un carro rebajado tiene que verse sin que nadie lo
    busque, y esta es la franja que se lee sin querer.

    Va en CSS y sin JavaScript. El contenido se escribe dos veces y se desplaza
    la mitad: al terminar vuelve al principio y el salto no se nota, que es el
    truco de toda cinta sin fin.
--}}
@php
    $enOferta = \App\Models\Unidad::query()
        ->where('publicado', true)
        ->publicables()
        ->enOferta()
        ->with(['marca', 'linea'])
        ->orderByRaw('(precio_lista - precio_oferta) / NULLIF(precio_lista, 0) desc')
        ->take(8)
        ->get();
@endphp

@if ($enOferta->isNotEmpty())
    @php
        // Unos siete segundos por oferta: con una sola, un recorrido largo la
        // deja parada fuera de la pantalla medio minuto; con ocho, uno corto
        // las pasa tan rápido que no se alcanzan a leer.
        $segundos = max(14, $enOferta->count() * 7);
    @endphp

    <div class="lotea-cinta border-b border-red-700/40 bg-red-600 text-white">
        <div class="lotea-cinta-pista flex w-max" style="--duracion: {{ $segundos }}s">
            {{-- Dos pasadas del mismo contenido: la segunda entra justo cuando
                 la primera se va, y por eso el bucle no tiene costura. --}}
            @foreach ([1, 2] as $pasada)
                <div class="flex shrink-0 items-center" data-pasada="{{ $pasada }}"
                     aria-hidden="{{ $pasada === 2 ? 'true' : 'false' }}">
                    @foreach ($enOferta as $oferta)
                        <a href="{{ \App\Support\PortalUrl::unidad($empresa, $oferta->slug) }}"
                           class="flex items-center gap-2 whitespace-nowrap px-5 py-2 text-sm transition hover:bg-red-700">
                            <span class="font-bold uppercase tracking-wide">{{ $oferta->etiqueta_de_oferta }}</span>
                            <span class="font-semibold">{{ $oferta->descripcion }}</span>
                            <span class="text-red-200">antes</span>
                            <span class="text-red-200 line-through">Q {{ number_format((float) $oferta->precio_lista, 0) }}</span>
                            <span class="text-red-200">ahora</span>
                            <span class="font-bold">Q {{ number_format((float) $oferta->precio_oferta, 0) }}</span>
                            <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs font-bold">
                                −{{ $oferta->descuento_porcentaje }}%
                            </span>
                        </a>

                        <span class="select-none px-1 text-red-300" aria-hidden="true">·</span>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
@endif
