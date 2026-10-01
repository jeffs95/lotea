{{--
    El aviso de las rebajas, al entrar.

    Un carro parado cuesta dinero todos los días, así que cuando se le baja el
    precio hay que decirlo: la mitad de los visitantes entran, miran dos fotos y
    se van sin llegar al catálogo.

    Se muestra una vez por visita y no en cada página. Un aviso que reaparece al
    navegar deja de ser una oferta y pasa a ser una molestia, y lo que se gana
    en atención se pierde en que cierren la pestaña.
--}}
@php
    $ofertas = \App\Models\Unidad::query()
        ->where('publicado', true)
        ->publicables()
        ->enOferta()
        ->with(['marca', 'linea', 'media'])
        ->orderByRaw('(precio_lista - precio_oferta) / NULLIF(precio_lista, 0) desc')
        ->take(4)
        ->get();
@endphp

@if ($ofertas->isNotEmpty())
    <div
        data-aviso-ofertas
        data-firma="{{ $ofertas->pluck('id')->join('-') }}"
        hidden
        class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="titulo-ofertas"
    >
        <div data-cerrar class="absolute inset-0 bg-gray-900/70"></div>

        <div
            class="relative max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl"
        >
            <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4 sm:px-7">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-red-600">
                        {{ $ofertas->count() === 1 ? 'Oferta' : 'Ofertas' }}
                    </p>
                    <h2 id="titulo-ofertas" class="mt-0.5 text-xl font-bold tracking-tight text-gray-900">
                        {{ $ofertas->count() === 1 ? 'Un vehículo con precio rebajado' : $ofertas->count().' vehículos con precio rebajado' }}
                    </h2>
                </div>

                <button type="button" data-cerrar
                        class="-m-2 shrink-0 rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-900"
                        aria-label="Cerrar">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="grid gap-3 p-5 sm:grid-cols-2 sm:p-7">
                @foreach ($ofertas as $oferta)
                    @php($foto = $oferta->getFirstMediaUrl('fotos', 'web') ?: null)

                    <a href="{{ \App\Support\PortalUrl::unidad($empresa, $oferta->slug) }}"
                       class="group flex gap-3 rounded-2xl p-2 transition hover:bg-gray-50">
                        <div class="relative h-20 w-28 shrink-0 overflow-hidden rounded-xl bg-gray-100">
                            @if ($foto)
                                <img src="{{ $foto }}" alt="{{ $oferta->descripcion }}" loading="lazy"
                                     class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                            @endif
                            <span class="absolute left-1 top-1 rounded-full bg-red-600 px-1.5 py-0.5 text-[0.65rem] font-bold text-white">
                                −{{ $oferta->descuento_porcentaje }}%
                            </span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-gray-900">{{ $oferta->descripcion }}</p>
                            <p class="text-xs text-gray-400 line-through">
                                Q {{ number_format((float) $oferta->precio_lista, 0) }}
                            </p>
                            <p class="text-lg font-bold leading-tight text-red-600">
                                Q {{ number_format((float) $oferta->precio_oferta, 0) }}
                            </p>
                            @if ($oferta->oferta_hasta)
                                <p class="text-[0.7rem] text-gray-500">
                                    Hasta el {{ $oferta->oferta_hasta->translatedFormat('j \d\e F') }}
                                </p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="border-t border-gray-100 px-5 py-4 sm:px-7">
                <a href="{{ \App\Support\PortalUrl::catalogo($empresa) }}"
                   class="block w-full rounded-xl px-4 py-3 text-center text-sm font-semibold text-white transition hover:opacity-90"
                   style="background: var(--acento)">
                    Ver todo el inventario
                </a>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const aviso = document.querySelector('[data-aviso-ofertas]');

            if (! aviso) {
                return;
            }

            const clave = 'lotea-ofertas-vistas';
            const firma = aviso.dataset.firma;

            /*
             * Lo visto se recuerda por la firma de las ofertas, no por un sí o
             * un no: cuando el concesionario rebaja otro carro la firma cambia
             * y el aviso vuelve a salir, que es justo cuando hay algo nuevo
             * que contar.
             *
             * En navegación privada el almacenamiento lanza en vez de
             * devolver vacío, así que todo va envuelto: sin esto el aviso no
             * saldría nunca para quien navega así.
             */
            try {
                if (sessionStorage.getItem(clave) === firma) {
                    return;
                }
            } catch (e) {}

            function cerrar() {
                aviso.hidden = true;

                try {
                    sessionStorage.setItem(clave, firma);
                } catch (e) {}
            }

            aviso.querySelectorAll('[data-cerrar]').forEach(function (boton) {
                boton.addEventListener('click', cerrar);
            });

            document.addEventListener('keydown', function (evento) {
                if (evento.key === 'Escape' && ! aviso.hidden) {
                    cerrar();
                }
            });

            // Un momento de respiro: que la página se vea antes de taparla.
            setTimeout(function () {
                aviso.hidden = false;
            }, 900);
        })();
    </script>
@endif
