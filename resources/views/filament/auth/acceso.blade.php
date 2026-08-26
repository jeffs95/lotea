{{--
    El layout de las dos entradas: marca a la izquierda, formulario a la derecha.

    No reusa «fi-simple-layout» porque ese centra su contenido en la pantalla y
    aquí hace falta partirla en dos. Lo de dentro del formulario sigue siendo de
    Filament, así que los campos y los errores se ven como en el resto.

    En teléfono la mitad de marca se reduce a una franja arriba: nadie escribe su
    contraseña haciendo scroll.
--}}
@php
    $livewire ??= null;
    $portada ??= null;

    $color = $portada['color'] ?? \App\Models\Empresa::COLOR_POR_DEFECTO;
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <div
        class="lotea-acceso grid min-h-screen grid-rows-[auto_1fr] lg:grid-cols-[1.1fr_1fr] lg:grid-rows-1"
        style="--marca: {{ $color }}"
    >

        {{-- ── La marca ──────────────────────────────────────────────── --}}
        <div class="lotea-portada relative flex flex-col overflow-hidden px-6 py-7 sm:px-10 lg:justify-between lg:px-14 lg:py-14">
            {{-- Malla de puntos: da textura sin competir con el texto. --}}
            <div class="lotea-portada-malla pointer-events-none absolute inset-0" aria-hidden="true"></div>
            <div class="lotea-portada-brillo pointer-events-none absolute inset-0" aria-hidden="true"></div>

            <div class="relative flex items-center gap-3">
                <x-isotipo-lotea :color="$color" class="h-10 w-10 lg:h-11 lg:w-11" />
                <div class="leading-tight">
                    <div class="text-lg font-semibold tracking-tight text-white">Lotea</div>
                    @if (filled($portada['etiqueta'] ?? null))
                        <div class="text-[0.7rem] font-medium uppercase tracking-[0.14em] text-white/55">
                            {{ $portada['etiqueta'] }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="relative mt-7 max-w-lg lg:mt-0">
                <h2 class="text-2xl font-semibold leading-tight tracking-tight text-white sm:text-3xl lg:text-[2.4rem]">
                    {{ $portada['titulo'] ?? '' }}
                </h2>

                @if (filled($portada['lema'] ?? null))
                    <p class="mt-3 text-base text-white/65 lg:text-lg">{{ $portada['lema'] }}</p>
                @endif

                @if (filled($portada['puntos'] ?? null))
                    <ul class="mt-8 hidden space-y-4 lg:block">
                        @foreach ($portada['puntos'] as $punto)
                            <li class="flex gap-3 text-[0.95rem] leading-relaxed text-white/80">
                                <svg class="mt-0.5 h-5 w-5 shrink-0" style="color: var(--marca)" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                </svg>
                                <span>{{ $punto }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="relative mt-10 hidden text-xs text-white/35 lg:block">
                Lotea · Sistema de gestión para concesionarios
            </div>
        </div>

        {{-- ── El formulario ─────────────────────────────────────────── --}}
        <div class="flex items-start justify-center bg-white px-6 py-10 sm:px-10 lg:items-center lg:py-12 dark:bg-gray-950">
            <div class="w-full max-w-sm">
                <main id="fi-main-content" tabindex="-1" class="w-full">
                    {{ $slot }}
                </main>

                @if (filled($portada['nota'] ?? null))
                    <p class="mt-8 border-t border-gray-100 pt-6 text-center text-xs leading-relaxed text-gray-500 dark:border-white/10 dark:text-gray-400">
                        {{ $portada['nota'] }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::layout.base>
