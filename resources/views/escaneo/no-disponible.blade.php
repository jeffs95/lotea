@php
    $whatsapp = \App\Support\WhatsApp::internacional($empresa->whatsapp ?: $empresa->telefono);
    $mensaje = rawurlencode("Hola, vi el vehículo con código {$unidad->codigo_qr} en su patio y quiero información.");
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Consultá por esta unidad · {{ $empresa->nombre_comercial ?? $empresa->nombre }}</title>
    {{-- Las iniciales sobre el color de la marca: a 16 píxeles un logo
         apaisado no se distingue, y sobre barra oscura desaparece. --}}
    <link rel="icon" type="image/svg+xml" href="{{ $empresa->favicon_pestana_url }}">
    @if ($empresa->favicon_url)
        <link rel="alternate icon" href="{{ $empresa->favicon_url }}">
    @endif
    @vite(['resources/css/app.css'])
    <style>:root { --acento: {{ $empresa->color_de_marca }}; }</style>
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-50 px-4 font-sans">
    <div class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-gray-200">
        @if ($empresa->logo_url)
            <img src="{{ $empresa->logo_url }}" alt="{{ $empresa->getFilamentName() }}" class="mx-auto h-12 w-auto">
        @else
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-xl text-lg font-bold text-white" style="background: var(--acento)">
                {{ $empresa->iniciales }}
            </span>
        @endif

        <h1 class="mt-5 text-xl font-bold text-gray-900">Este vehículo todavía no está publicado</h1>

        <p class="mt-2 text-gray-600">
            Está en el patio, pero aún no tiene su ficha en línea. Escribinos y te pasamos los detalles y el precio.
        </p>

        <p class="mt-4 inline-block rounded-lg bg-gray-100 px-3 py-1.5 font-mono text-sm text-gray-700">
            Código {{ $unidad->codigo_qr }}
        </p>

        @if ($whatsapp)
            <a href="https://wa.me/{{ $whatsapp }}?text={{ $mensaje }}" target="_blank" rel="noopener"
               class="mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-green-600 px-4 py-3 font-semibold text-white transition hover:bg-green-700">
                Preguntar por WhatsApp
            </a>
        @endif

        <a href="{{ \App\Support\PortalUrl::catalogo($empresa) }}"
           class="mt-2 block w-full rounded-xl border border-gray-300 px-4 py-3 font-semibold text-gray-700 transition hover:bg-gray-50">
            Ver los que sí están disponibles
        </a>
    </div>
</body>
</html>
