@extends('portal.layout')

@section('titulo', ($empresa->nombre_comercial ?? $empresa->nombre) . ' · Vehículos de importación')
@section('descripcion', 'Vehículos importados con garantía. ' . $total . ' unidades disponibles en ' . ($empresa->nombre_comercial ?? $empresa->nombre) . '.')

@section('contenido')
    <section class="relative overflow-hidden bg-gray-900">
        <div class="absolute inset-0 opacity-20" style="background: radial-gradient(60% 60% at 70% 20%, var(--acento), transparent)"></div>

        <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24">
            @if ($empresa->logo_oscuro_url)
                <img src="{{ $empresa->logo_oscuro_url }}" alt="{{ $empresa->getFilamentName() }}"
                     class="mb-6 h-12 w-auto object-contain sm:h-16">
            @endif

            <p class="text-sm font-semibold uppercase tracking-widest" style="color: var(--acento)">
                {{ $total }} {{ $total === 1 ? 'unidad disponible' : 'unidades disponibles' }}
            </p>

            <h1 class="mt-3 max-w-3xl text-4xl font-bold leading-tight tracking-tight text-white sm:text-5xl">
                Carros de importación,<br>con historia clara y precio de frente.
            </h1>

            <p class="mt-4 max-w-xl text-lg text-gray-300">
                Traemos las unidades directo de subasta, las preparamos en nuestro taller y te las entregamos
                listas para rodar.
            </p>

            {{-- Buscador: lo primero que hace la gente es escribir el modelo que quiere --}}
            <form action="{{ \App\Support\PortalUrl::catalogo($empresa) }}" method="GET"
                  class="mt-8 flex max-w-xl gap-2">
                <input type="search" name="q" placeholder="Buscá por marca, modelo o número de stock"
                       class="w-full rounded-xl border-0 px-4 py-3 text-base shadow-lg ring-1 ring-gray-300 focus:ring-2 focus:ring-gray-900">
                <button type="submit"
                        class="shrink-0 rounded-xl px-5 py-3 font-semibold text-white shadow-lg transition hover:opacity-90"
                        style="background: var(--acento)">
                    Buscar
                </button>
            </form>
        </div>
    </section>

    @if ($marcas->isNotEmpty())
        <section class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-7xl flex-wrap gap-2 px-4 py-4 sm:px-6">
                @foreach ($marcas as $marca)
                    <a href="{{ \App\Support\PortalUrl::catalogo($empresa, ['marca' => $marca->slug]) }}"
                       class="rounded-full border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:border-gray-400 hover:bg-gray-50">
                        {{ $marca->nombre }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($destacadas->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6">
            <h2 class="text-2xl font-bold tracking-tight">Destacados</h2>
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($destacadas as $unidad)
                    @include('portal.componentes.tarjeta', ['unidad' => $unidad])
                @endforeach
            </div>
        </section>
    @endif

    <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6">
        <div class="flex items-end justify-between">
            <h2 class="text-2xl font-bold tracking-tight">Recién ingresados</h2>
            <a href="{{ \App\Support\PortalUrl::catalogo($empresa) }}" class="text-sm font-semibold hover:underline" style="color: var(--acento)">
                Ver todo el inventario →
            </a>
        </div>

        @if ($recientes->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-gray-300 px-6 py-12 text-center text-gray-500">
                Todavía no hay unidades publicadas.
            </p>
        @else
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($recientes as $unidad)
                    @include('portal.componentes.tarjeta', ['unidad' => $unidad])
                @endforeach
            </div>
        @endif
    </section>
@endsection
