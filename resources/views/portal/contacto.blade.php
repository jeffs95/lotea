@extends('portal.layout')

@section('titulo', 'Dónde encontrarnos · ' . ($empresa->nombre_comercial ?? $empresa->nombre))
@section('descripcion', 'Visitá nuestros patios o escribinos. ' . ($empresa->nombre_comercial ?? $empresa->nombre) . ' en Guatemala.')

@section('contenido')
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">

        <div class="max-w-2xl">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Encontranos aquí</h1>
            <p class="mt-3 text-lg text-gray-600">
                Vení a ver los carros en persona. Te esperamos sin cita.
            </p>
        </div>

        {{-- Los patios --}}
        <div class="mt-10 grid gap-6 lg:grid-cols-2">
            @forelse ($sucursales as $sucursal)
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">{{ $sucursal->nombre }}</h2>
                            @if ($sucursal->es_principal)
                                <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-semibold text-white"
                                      style="background: var(--acento)">Casa matriz</span>
                            @endif
                        </div>
                    </div>

                    @if ($sucursal->direccion)
                        <p class="mt-4 flex gap-2 text-sm text-gray-600">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                            </svg>
                            <span>{{ $sucursal->direccion }}</span>
                        </p>
                    @endif

                    @if ($sucursal->horario)
                        <p class="mt-2 flex gap-2 text-sm text-gray-600">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <span>{{ $sucursal->horario }}</span>
                        </p>
                    @endif

                    @if ($sucursal->telefono)
                        <p class="mt-2 flex gap-2 text-sm text-gray-600">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                            </svg>
                            <a href="tel:{{ $sucursal->telefono }}" class="hover:underline">{{ $sucursal->telefono }}</a>
                        </p>
                    @endif

                    {{-- Cómo llegar: en Guatemala casi todo el mundo maneja con Waze --}}
                    @if ($sucursal->tieneUbicacion())
                        <div class="mt-5 flex flex-wrap gap-2">
                            <a href="{{ $sucursal->mapa_google }}" target="_blank" rel="noopener"
                               class="rounded-lg px-3 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                               style="background: var(--acento)">
                                Cómo llegar
                            </a>
                            <a href="{{ $sucursal->mapa_waze }}" target="_blank" rel="noopener"
                               class="rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-200">
                                Abrir en Waze
                            </a>
                            @if ($sucursal->whatsapp_internacional)
                                <a href="https://wa.me/{{ $sucursal->whatsapp_internacional }}" target="_blank" rel="noopener"
                                   class="rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-200">
                                    WhatsApp
                                </a>
                            @endif
                        </div>
                    @elseif ($sucursal->whatsapp_internacional)
                        <div class="mt-5">
                            <a href="https://wa.me/{{ $sucursal->whatsapp_internacional }}" target="_blank" rel="noopener"
                               class="rounded-lg px-3 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                               style="background: var(--acento)">
                                Escribir por WhatsApp
                            </a>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 px-6 py-16 text-center lg:col-span-2">
                    <p class="text-lg font-semibold text-gray-900">Escribinos y coordinamos</p>
                    <p class="mt-1 text-gray-500">Con gusto te atendemos por teléfono o WhatsApp.</p>
                </div>
            @endforelse
        </div>

        {{-- Contactanos --}}
        <div class="mt-14 grid gap-8 rounded-2xl bg-gray-900 p-8 text-white sm:p-10 lg:grid-cols-2">
            <div>
                <h2 class="text-2xl font-bold">Contactanos</h2>
                <p class="mt-2 text-gray-300">
                    Escribinos por donde te quede mejor. Contestamos rápido.
                </p>

                <div class="mt-6 space-y-3">
                    @if ($empresa->whatsapp_enlace)
                        <a href="{{ $empresa->whatsapp_enlace }}" target="_blank" rel="noopener"
                           class="flex w-full items-center justify-center gap-2 rounded-lg px-4 py-3 font-semibold text-white transition hover:opacity-90 sm:w-auto sm:justify-start sm:px-5"
                           style="background: var(--acento)">
                            Escribir por WhatsApp
                        </a>
                    @endif

                    @if ($empresa->telefono)
                        <p class="text-gray-300">
                            Teléfono:
                            <a href="tel:{{ $empresa->telefono }}" class="font-semibold text-white hover:underline">{{ $empresa->telefono }}</a>
                        </p>
                    @endif

                    @if ($empresa->email)
                        <p class="text-gray-300">
                            Correo:
                            <a href="mailto:{{ $empresa->email }}" class="font-semibold text-white hover:underline">{{ $empresa->email }}</a>
                        </p>
                    @endif
                </div>

                @if (count($empresa->redes))
                    <div class="mt-8">
                        <p class="text-sm font-semibold uppercase tracking-wide text-gray-400">Seguinos</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($empresa->redes as $red)
                                <a href="{{ $red['url'] }}" target="_blank" rel="noopener"
                                   class="rounded-lg bg-white/10 px-4 py-2 text-sm font-semibold transition hover:bg-white/20">
                                    {{ $red['nombre'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- El mismo formulario que ya recoge los prospectos --}}
            <div class="rounded-xl bg-white p-6 text-gray-900">
                <p class="text-lg font-bold">Dejanos tus datos</p>
                <p class="mt-1 text-sm text-gray-500">Te contactamos nosotros.</p>

                @include('portal.componentes.formulario-contacto')
            </div>
        </div>
    </div>
@endsection
