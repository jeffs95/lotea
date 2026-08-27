@extends('portal.layout')

@section('titulo', 'Encuéntranos aquí · ' . ($empresa->nombre_comercial ?? $empresa->nombre))
@section('descripcion', 'Ven a conocer nuestros vehículos o escríbenos. ' . ($empresa->nombre_comercial ?? $empresa->nombre) . ' en Guatemala.')

@section('contenido')

    {{-- Mismo lenguaje que el inicio: oscuro con el color de la marca detrás --}}
    <section class="relative overflow-hidden bg-gray-900">
        @if ($empresa->portada_url)
            {{-- La foto del cliente, y encima una capa oscura: sin ella el
                 titular blanco se pierde sobre una foto clara y la portada
                 queda ilegible, que es peor que no tener foto. --}}
            <img src="{{ $empresa->portada_url }}" alt=""
                 class="absolute inset-0 h-full w-full object-cover" loading="eager">
            <div class="absolute inset-0 bg-gray-900/75"></div>
            <div class="absolute inset-0 opacity-25" style="background: radial-gradient(60% 60% at 70% 20%, var(--acento), transparent)"></div>
        @else
            <div class="absolute inset-0 opacity-20" style="background: radial-gradient(60% 60% at 70% 20%, var(--acento), transparent)"></div>
        @endif

        <div class="relative mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-20">
            @if ($empresa->logo_oscuro_url)
                {{-- Sobre el hero oscuro va la versión de trazo claro --}}
                <img src="{{ $empresa->logo_oscuro_url }}" alt="{{ $empresa->getFilamentName() }}"
                     class="mb-6 h-12 w-auto object-contain sm:h-14">
            @endif

            <p class="text-sm font-semibold uppercase tracking-widest" style="color: var(--acento)">
                {{ $sucursales->count() === 1 ? 'Nuestro patio' : 'Nuestros patios' }}
            </p>

            <h1 class="mt-3 max-w-3xl text-4xl font-bold leading-tight tracking-tight text-white sm:text-5xl">
                Encuéntranos aquí.
            </h1>

            <p class="mt-4 max-w-xl text-lg text-gray-300">
                Ven a conocer nuestros vehículos en persona, sin necesidad de cita. También puedes comunicarte con nosotros por el medio que prefieras.
            </p>

            @if ($empresa->whatsapp_enlace)
                <a href="{{ $empresa->whatsapp_enlace }}" target="_blank" rel="noopener"
                   class="mt-8 inline-flex items-center gap-2 rounded-xl px-5 py-3 font-semibold text-white shadow-lg transition hover:opacity-90"
                   style="background: var(--acento)">
                    @include('portal.componentes.icono-red', ['red' => 'whatsapp'])
                    Escríbenos por WhatsApp
                </a>
            @endif
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16">

        {{-- Los patios, cada uno con su mapa --}}
        <div class="space-y-8">
            @forelse ($sucursales as $sucursal)
                <div class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="grid lg:grid-cols-5">

                        {{-- El mapa manda: es lo que la gente mira primero --}}
                        <div class="relative min-h-64 bg-gray-100 lg:col-span-3">
                            @if ($sucursal->tieneUbicacion())
                                <iframe
                                    src="https://maps.google.com/maps?q={{ $sucursal->punto }}&z=16&output=embed"
                                    class="h-full min-h-64 w-full border-0"
                                    loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade"
                                    title="Ubicación de {{ $sucursal->nombre }}"
                                    allowfullscreen></iframe>
                            @else
                                {{-- Sin coordenadas no hay mapa, pero la tarjeta no se puede
                                     ver rota: se llena con la identidad del concesionario. --}}
                                <div class="flex h-full min-h-64 flex-col items-center justify-center gap-3 bg-gray-50 p-8 text-center">
                                    <span class="grid h-14 w-14 place-items-center rounded-2xl text-lg font-bold text-white"
                                          style="background: var(--acento)">
                                        {{ $empresa->iniciales }}
                                    </span>
                                    <p class="text-sm text-gray-500">
                                        Llámanos y te damos la referencia exacta para llegar.
                                    </p>
                                </div>
                            @endif
                        </div>

                        {{-- Los datos --}}
                        <div class="flex flex-col justify-center gap-5 p-7 sm:p-8 lg:col-span-2">
                            <div>
                                @if ($sucursal->es_principal && $sucursales->count() > 1)
                                    <span class="mb-2 inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold text-white"
                                          style="background: var(--acento)">Casa matriz</span>
                                @endif

                                <h2 class="text-2xl font-bold tracking-tight text-gray-900">{{ $sucursal->nombreConLaEmpresa() }}</h2>
                            </div>

                            <dl class="space-y-3.5 text-sm">
                                @if ($sucursal->direccion)
                                    <div class="flex gap-3">
                                        <dt class="mt-0.5 shrink-0 text-gray-400">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                            </svg>
                                        </dt>
                                        <dd class="text-gray-600">{{ $sucursal->direccion }}</dd>
                                    </div>
                                @endif

                                @if ($sucursal->horario)
                                    <div class="flex gap-3">
                                        <dt class="mt-0.5 shrink-0 text-gray-400">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                            </svg>
                                        </dt>
                                        <dd class="text-gray-600">{{ $sucursal->horario }}</dd>
                                    </div>
                                @endif

                                @if ($sucursal->telefono)
                                    <div class="flex gap-3">
                                        <dt class="mt-0.5 shrink-0 text-gray-400">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                                            </svg>
                                        </dt>
                                        <dd>
                                            <a href="tel:{{ $sucursal->telefono }}" class="font-medium text-gray-900 hover:underline">
                                                {{ $sucursal->telefono }}
                                            </a>
                                        </dd>
                                    </div>
                                @endif
                            </dl>

                            <div class="flex flex-wrap gap-2 pt-1">
                                @if ($sucursal->tieneUbicacion())
                                    <a href="{{ $sucursal->mapa_google }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90"
                                       style="background: var(--acento)">
                                        Cómo llegar
                                    </a>
                                    <a href="{{ $sucursal->mapa_waze }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-1.5 rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-200">
                                        Abrir en Waze
                                    </a>
                                @endif

                                @if ($sucursal->whatsapp_internacional)
                                    <a href="https://wa.me/{{ $sucursal->whatsapp_internacional }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-1.5 rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-200">
                                        @include('portal.componentes.icono-red', ['red' => 'whatsapp', 'clases' => 'h-4 w-4'])
                                        WhatsApp
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-3xl border border-dashed border-gray-300 px-6 py-16 text-center">
                    <p class="text-lg font-semibold text-gray-900">Escríbenos y coordinamos</p>
                    <p class="mt-1 text-gray-500">Con gusto te atendemos por teléfono o por WhatsApp.</p>
                </div>
            @endforelse
        </div>

        {{-- Solo el formulario.
             Aquí había además una columna con WhatsApp, teléfono, correo y
             redes: exactamente lo que ya está en el pie de todas las páginas.
             El visitante leía lo mismo dos veces seguidas y había dos sitios
             que mantener sincronizados. --}}
        <div id="contacto" class="mt-16 max-w-2xl">

            {{-- El mismo formulario que ya recoge los prospectos --}}
            <div class="rounded-3xl bg-white p-7 shadow-sm ring-1 ring-gray-200 sm:p-8">
                <p class="text-xl font-bold text-gray-900">Solicita más información</p>
                <p class="mt-1 text-sm text-gray-500">Déjanos tus datos y nos comunicaremos contigo, sin compromiso.</p>

                @include('portal.componentes.formulario-contacto')
            </div>
        </div>
    </div>
@endsection
