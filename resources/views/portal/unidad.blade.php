@extends('portal.layout')

@php
    $fotos = $unidad->getMedia('fotos');
    $portada = $fotos->first()?->getUrl('web');
    $enCamino = $unidad->estado->etapa() !== 'venta';
    $whatsapp = \App\Support\WhatsApp::internacional($empresa->whatsapp ?: $empresa->telefono);
    $mensajeWa = rawurlencode("Hola, me interesa el {$unidad->descripcion} (stock {$unidad->stock_no}) que vi en su sitio.");
    $tipo = $unidad->tipo_vehiculo;
    $forma = \App\Filament\Resources\Unidades\Schemas\UnidadForm::class;

    // La ficha se arma según lo que sea: una moto no tiene puertas y sí
    // cilindrada, que es lo primero que preguntan.
    $especificaciones = collect([
        'Año' => $unidad->anio,
        'Kilometraje' => $unidad->odometro ? number_format($unidad->odometro) . ' ' . ($unidad->odometro_unidad === 'mi' ? 'millas' : 'km') : null,
        'Cilindrada' => $unidad->cilindrada_cc ? $unidad->cilindrada_cc . ' cc' : null,
        ($tipo->esMoto() ? 'Estilo' : 'Tipo') => $tipo->carrocerias()[$unidad->carroceria] ?? null,
        'Transmisión' => $tipo->transmisiones()[$unidad->transmision] ?? null,
        'Combustible' => $forma::COMBUSTIBLES[$unidad->combustible] ?? null,
        'Tracción' => $tipo->aplica('traccion') ? ($forma::TRACCIONES[$unidad->traccion] ?? null) : null,
        'Motor' => $unidad->motor,
        'Color' => $unidad->color,
        'Puertas' => $tipo->aplica('puertas') ? $unidad->puertas : null,
    ])->filter();
@endphp

@section('titulo', $unidad->descripcion . ' · Stock ' . $unidad->stock_no)
@section('descripcion', $unidad->descripcion . ($unidad->odometro ? ' con ' . number_format($unidad->odometro) . ' ' . $unidad->odometro_unidad : '') . '. Q' . number_format((float) $unidad->precio_lista, 0) . '.')
@section('og_tipo', 'product')
@section('og_imagen', $portada ?? '')

@section('schema')
    {{-- Para que Google entienda que esto es un carro en venta y no un artículo --}}
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Car',
        'name' => $unidad->descripcion,
        'sku' => $unidad->stock_no,
        'vehicleIdentificationNumber' => $unidad->vin,
        'modelDate' => (string) $unidad->anio,
        'brand' => $unidad->marca ? ['@type' => 'Brand', 'name' => $unidad->marca->nombre] : null,
        'model' => $unidad->linea?->nombre,
        'color' => $unidad->color,
        'image' => $portada,
        'mileageFromOdometer' => $unidad->odometro ? [
            '@type' => 'QuantitativeValue',
            'value' => $unidad->odometro,
            'unitCode' => $unidad->odometro_unidad === 'mi' ? 'SMI' : 'KMT',
        ] : null,
        'offers' => [
            '@type' => 'Offer',
            'price' => (float) $unidad->precio_lista,
            'priceCurrency' => 'GTQ',
            'availability' => $enCamino ? 'https://schema.org/PreOrder' : 'https://schema.org/InStock',
            'seller' => ['@type' => 'AutoDealer', 'name' => $empresa->nombre_comercial ?? $empresa->nombre],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endsection

@section('contenido')
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">

        <nav class="mb-5 text-sm text-gray-500">
            <a href="{{ \App\Support\PortalUrl::catalogo($empresa) }}" class="hover:text-gray-900">Vehículos</a>
            <span class="mx-1.5">/</span>
            <span class="text-gray-900">{{ $unidad->descripcion }}</span>
        </nav>

        <div class="grid gap-8 lg:grid-cols-[1fr_380px]">

            {{-- Galería y datos --}}
            <div>
                <div class="overflow-hidden rounded-2xl bg-gray-100 shadow-sm ring-1 ring-gray-200">
                    @if ($portada)
                        <img id="foto-principal" src="{{ $portada }}" alt="{{ $unidad->descripcion }}"
                             class="aspect-[4/3] w-full object-cover">
                    @else
                        <div class="flex aspect-[4/3] w-full flex-col items-center justify-center gap-3 bg-gradient-to-br from-gray-100 to-gray-200">
                            <svg class="h-16 w-16 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-8.25m0-11.25h5.379a2.25 2.25 0 011.59.659l2.253 2.252m-9.222 8.339H2.25V6.375c0-.621.504-1.125 1.125-1.125H9.75" />
                            </svg>
                            <p class="text-sm text-gray-400">Estamos tomando las fotos de esta unidad</p>
                        </div>
                    @endif
                </div>

                @if ($fotos->count() > 1)
                    <div class="mt-3 grid grid-cols-5 gap-2 sm:grid-cols-8">
                        @foreach ($fotos as $foto)
                            <button type="button" onclick="document.getElementById('foto-principal').src = '{{ $foto->getUrl('web') }}'"
                                    class="overflow-hidden rounded-lg ring-1 ring-gray-200 transition hover:ring-2 hover:ring-gray-400">
                                <img src="{{ $foto->getUrl('miniatura') }}" alt="" loading="lazy" class="aspect-[4/3] w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif

                <section class="mt-8 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 class="text-lg font-bold">Ficha técnica</h2>
                    <dl class="mt-4 grid gap-x-8 gap-y-3 sm:grid-cols-2">
                        @foreach ($especificaciones as $etiqueta => $valor)
                            <div class="flex justify-between border-b border-gray-100 pb-2">
                                <dt class="text-sm text-gray-500">{{ $etiqueta }}</dt>
                                <dd class="text-sm font-medium text-gray-900">{{ $valor }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>

                @if ($unidad->descripcion_comercial)
                    <section class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <h2 class="text-lg font-bold">Sobre esta unidad</h2>
                        <p class="mt-3 whitespace-pre-line text-gray-700">{{ $unidad->descripcion_comercial }}</p>
                    </section>
                @endif
            </div>

            {{-- Precio, financiamiento y contacto --}}
            <div class="space-y-5 lg:sticky lg:top-20 lg:self-start">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    @if ($enCamino)
                        <span class="inline-block rounded-full bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white">
                            Próximamente · viene en camino
                        </span>
                    @endif

                    <p class="mt-2 text-xs font-medium text-gray-400">Stock {{ $unidad->stock_no }}</p>
                    <h1 class="mt-1 text-2xl font-bold leading-tight">{{ $unidad->descripcion }}</h1>
                    <p class="mt-3 text-3xl font-bold" style="color: var(--acento)">
                        Q {{ number_format((float) $unidad->precio_lista, 0) }}
                    </p>

                    @if ($unidad->sucursal)
                        <p class="mt-2 text-sm text-gray-500">Disponible en {{ $unidad->sucursal->nombre }}</p>
                    @endif

                    @if ($whatsapp)
                        <a href="https://wa.me/{{ $whatsapp }}?text={{ $mensajeWa }}" target="_blank" rel="noopener"
                           class="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-green-600 px-4 py-3 font-semibold text-white transition hover:bg-green-700">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884a9.82 9.82 0 016.988 2.896 9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                            Preguntar por WhatsApp
                        </a>
                    @endif

                    <a href="#contacto" class="mt-2 flex w-full items-center justify-center rounded-xl border border-gray-300 px-4 py-3 font-semibold text-gray-700 transition hover:bg-gray-50">
                        Dejar mis datos
                    </a>
                </div>

                {{-- Calculadora: la gente no compra el precio, compra la cuota --}}
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 class="text-base font-bold">Calcule su cuota</h2>
                    <p class="mt-1 text-xs text-gray-500">Estimado referencial, sujeto a aprobación.</p>

                    <div class="mt-4 space-y-4" data-calculadora data-precio="{{ (float) $unidad->precio_lista }}">
                        <div>
                            <div class="flex justify-between text-sm">
                                <label class="font-medium text-gray-700">Enganche</label>
                                <span data-enganche-texto class="font-semibold text-gray-900"></span>
                            </div>
                            <input type="range" data-enganche min="10" max="60" step="5" value="30" class="mt-2 w-full accent-gray-900">
                        </div>

                        <div>
                            <div class="flex justify-between text-sm">
                                <label class="font-medium text-gray-700">Plazo</label>
                                <span data-plazo-texto class="font-semibold text-gray-900"></span>
                            </div>
                            <input type="range" data-plazo min="12" max="60" step="6" value="36" class="mt-2 w-full accent-gray-900">
                        </div>

                        <div class="rounded-xl bg-gray-50 p-4 text-center">
                            <p class="text-xs font-medium text-gray-500">Cuota mensual estimada</p>
                            <p data-cuota class="mt-1 text-2xl font-bold text-gray-900"></p>
                            <p class="mt-1 text-xs text-gray-400">Tasa referencial 16% anual</p>
                        </div>
                    </div>
                </div>

                {{-- Formulario de contacto --}}
                <div id="contacto" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    @include('portal.componentes.formulario-contacto', [
                        'unidad' => $unidad,
                        'titulo' => '¿Le interesa? Déjenos sus datos',
                    ])
                </div>
            </div>
        </div>

        @if ($similares->isNotEmpty())
            <section class="mt-14">
                <h2 class="text-2xl font-bold tracking-tight">Otros {{ $unidad->marca?->nombre }} disponibles</h2>
                <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($similares as $similar)
                        @include('portal.componentes.tarjeta', ['unidad' => $similar])
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    <script>
        document.querySelectorAll('[data-calculadora]').forEach(function (caja) {
            const precio = parseFloat(caja.dataset.precio || 0);
            const engancheInput = caja.querySelector('[data-enganche]');
            const plazoInput = caja.querySelector('[data-plazo]');
            const quetzales = new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ', maximumFractionDigits: 0 });

            function calcular() {
                const porcentaje = parseInt(engancheInput.value, 10);
                const meses = parseInt(plazoInput.value, 10);
                const enganche = precio * (porcentaje / 100);
                const financiado = precio - enganche;
                const tasaMensual = 0.16 / 12;

                // Cuota nivelada. Es un estimado para que el cliente se haga
                // una idea, no una oferta de crédito.
                const cuota = financiado > 0
                    ? (financiado * tasaMensual) / (1 - Math.pow(1 + tasaMensual, -meses))
                    : 0;

                caja.querySelector('[data-enganche-texto]').textContent = quetzales.format(enganche) + ' (' + porcentaje + '%)';
                caja.querySelector('[data-plazo-texto]').textContent = meses + ' meses';
                caja.querySelector('[data-cuota]').textContent = quetzales.format(cuota);
            }

            engancheInput.addEventListener('input', calcular);
            plazoInput.addEventListener('input', calcular);
            calcular();
        });
    </script>
@endsection
