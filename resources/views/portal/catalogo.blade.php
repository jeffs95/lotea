@extends('portal.layout')

@section('titulo', 'Vehículos disponibles · ' . ($empresa->nombre_comercial ?? $empresa->nombre))
@section('descripcion', 'Inventario completo de vehículos de importación con filtros por marca, año, precio y sucursal.')

@section('contenido')
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <h1 class="text-3xl font-bold tracking-tight">Vehículos disponibles</h1>
        <p class="mt-1 text-gray-600">{{ $unidades->total() }} {{ $unidades->total() === 1 ? 'unidad' : 'unidades' }} en inventario</p>

        <div class="mt-8 grid gap-8 lg:grid-cols-[280px_1fr]">

            {{-- Filtros --}}
            <aside class="lg:sticky lg:top-20 lg:self-start">
                <form method="GET" class="space-y-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <div>
                        <label class="block text-sm font-semibold text-gray-900">Buscar</label>
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Marca, modelo, stock"
                               class="mt-1.5 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-900">Marca</label>
                        <select name="marca" class="mt-1.5 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                            <option value="">Todas</option>
                            @foreach ($marcas as $marca)
                                <option value="{{ $marca->slug }}" @selected(request('marca') === $marca->slug)>{{ $marca->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-semibold text-gray-900">Año desde</label>
                            <input type="number" name="anio_min" value="{{ request('anio_min') }}" min="1990" max="{{ date('Y') + 1 }}"
                                   class="mt-1.5 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-900">Hasta</label>
                            <input type="number" name="anio_max" value="{{ request('anio_max') }}" min="1990" max="{{ date('Y') + 1 }}"
                                   class="mt-1.5 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-900">Precio máximo</label>
                        <input type="number" name="precio_max" value="{{ request('precio_max') }}" step="5000" placeholder="Q"
                               class="mt-1.5 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-900">Transmisión</label>
                        <select name="transmision" class="mt-1.5 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                            <option value="">Cualquiera</option>
                            @foreach (\App\Filament\Resources\Unidades\Schemas\UnidadForm::TRANSMISIONES as $valor => $etiqueta)
                                <option value="{{ $valor }}" @selected(request('transmision') === $valor)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-900">Qué buscás</label>
                        <select name="tipo_vehiculo" class="mt-1.5 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                            <option value="">Todo</option>
                            @foreach (\App\Enums\TipoVehiculo::opciones() as $valor => $etiqueta)
                                <option value="{{ $valor }}" @selected(request('tipo_vehiculo') === $valor)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-900">Tipo</label>
                        <select name="carroceria" class="mt-1.5 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                            <option value="">Cualquiera</option>
                            @foreach (\App\Enums\TipoVehiculo::todasLasCarrocerias() as $valor => $etiqueta)
                                <option value="{{ $valor }}" @selected(request('carroceria') === $valor)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($sucursales->count() > 1)
                        <div>
                            <label class="block text-sm font-semibold text-gray-900">Sucursal</label>
                            <select name="sucursal" class="mt-1.5 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                                <option value="">Todas</option>
                                @foreach ($sucursales as $sucursal)
                                    <option value="{{ $sucursal->codigo }}" @selected(request('sucursal') === $sucursal->codigo)>{{ $sucursal->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="flex gap-2 pt-1">
                        <button type="submit" class="flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90" style="background: var(--acento)">
                            Filtrar
                        </button>
                        <a href="{{ \App\Support\PortalUrl::catalogo($empresa) }}"
                           class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                            Limpiar
                        </a>
                    </div>
                </form>
            </aside>

            {{-- Resultados --}}
            <div>
                {{-- Lo primero que mira el comprador: qué tipo de vehículo quiere --}}
                @include('portal.componentes.segmentos', ['tipos' => $tipos, 'carrocerias' => $carrocerias])

                <form method="GET" class="mb-5 flex justify-end">
                    @foreach (request()->except('orden', 'page') as $clave => $valor)
                        <input type="hidden" name="{{ $clave }}" value="{{ $valor }}">
                    @endforeach
                    <select name="orden" onchange="this.form.submit()"
                            class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                        @foreach ($ordenes as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected(request('orden') === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </form>

                @if ($unidades->isEmpty())
                    <div class="rounded-2xl border border-dashed border-gray-300 px-6 py-20 text-center">
                        <p class="text-lg font-semibold text-gray-900">No encontramos nada con esos filtros</p>
                        <p class="mt-1 text-gray-500">Pruebe quitando alguno, o escríbanos y le avisamos cuando entre algo así.</p>
                        <a href="{{ \App\Support\PortalUrl::catalogo($empresa) }}" class="mt-4 inline-block text-sm font-semibold hover:underline" style="color: var(--acento)">
                            Ver todo el inventario
                        </a>
                    </div>
                @else
                    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($unidades as $unidad)
                            @include('portal.componentes.tarjeta', ['unidad' => $unidad])
                        @endforeach
                    </div>

                    <div class="mt-8">{{ $unidades->links() }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
