{{--
    La segmentación de arriba del catálogo: primero por tipo (carros, motos,
    camiones) y debajo por carrocería (sedán, camioneta, pick-up).

    Solo salen los que el concesionario tiene a la venta: un botón que lleva a
    una página vacía pierde al comprador.
--}}
@php
    $sinTipo = request()->except(['tipo_vehiculo', 'carroceria', 'page']);
    $tipoActivo = request('tipo_vehiculo');
    $carroceriaActiva = request('carroceria');
@endphp

@if (count($tipos) > 1 || count($carrocerias) > 1)
    <div class="mb-6 space-y-3">
        @if (count($tipos) > 1)
            <div class="flex flex-wrap gap-2">
                <a href="?{{ \Illuminate\Support\Arr::query($sinTipo) }}"
                   @class([
                       'rounded-full px-4 py-2 text-sm font-semibold transition',
                       'text-white' => ! $tipoActivo,
                       'bg-white text-gray-700 ring-1 ring-gray-200 hover:ring-gray-400' => $tipoActivo,
                   ])
                   @style(['background: var(--acento)' => ! $tipoActivo])>
                    Todo
                </a>

                @foreach ($tipos as $tipo)
                    <a href="?{{ \Illuminate\Support\Arr::query([...$sinTipo, 'tipo_vehiculo' => $tipo['valor']]) }}"
                       @class([
                           'rounded-full px-4 py-2 text-sm font-semibold transition',
                           'text-white' => $tipoActivo === $tipo['valor'],
                           'bg-white text-gray-700 ring-1 ring-gray-200 hover:ring-gray-400' => $tipoActivo !== $tipo['valor'],
                       ])
                       @style(['background: var(--acento)' => $tipoActivo === $tipo['valor']])>
                        {{ $tipo['etiqueta'] }}
                        <span class="ml-1 opacity-60">{{ $tipo['total'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        @if (count($carrocerias) > 1)
            <div class="flex flex-wrap gap-2">
                @foreach ($carrocerias as $carroceria)
                    @php
                        $activa = $carroceriaActiva === $carroceria['valor'];
                        $sinCarroceria = request()->except(['carroceria', 'page']);
                        $destino = $activa
                            ? $sinCarroceria
                            : [...$sinCarroceria, 'carroceria' => $carroceria['valor']];
                    @endphp

                    <a href="?{{ \Illuminate\Support\Arr::query($destino) }}"
                       @class([
                           'rounded-lg px-3 py-1.5 text-xs font-medium transition',
                           'bg-gray-900 text-white' => $activa,
                           'bg-gray-100 text-gray-600 hover:bg-gray-200' => ! $activa,
                       ])>
                        {{ $carroceria['etiqueta'] }}
                        <span class="ml-1 opacity-60">{{ $carroceria['total'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endif
