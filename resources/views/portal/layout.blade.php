<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('titulo', $empresa->nombre_comercial ?? $empresa->nombre)</title>
    <meta name="description" content="@yield('descripcion', 'Vehículos de importación con garantía en ' . ($empresa->nombre_comercial ?? $empresa->nombre) . '.')">

    {{-- Que se vea bien cuando lo comparten por WhatsApp, que es como circula todo aquí --}}
    <meta property="og:type" content="@yield('og_tipo', 'website')">
    <meta property="og:title" content="@yield('titulo', $empresa->nombre_comercial ?? $empresa->nombre)">
    <meta property="og:description" content="@yield('descripcion', 'Vehículos de importación con garantía.')">
    {{-- Lo que se ve al pegar el enlace en WhatsApp, que es como circula todo
         aquí. Si la página no trae foto de un carro, va el logo. --}}
    <meta property="og:image" content="@yield('og_imagen', $empresa->logo_url ? url($empresa->logo_url) : '')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">

    @yield('schema')

    @vite(['resources/css/app.css'])

    {{-- Las iniciales sobre el color de la marca: a 16 píxeles un logo
         apaisado no se distingue, y sobre barra oscura desaparece. --}}
    <link rel="icon" type="image/svg+xml" href="{{ $empresa->favicon_pestana_url }}">
    @if ($empresa->favicon_url)
        <link rel="alternate icon" href="{{ $empresa->favicon_url }}">
    @endif

    <style>:root { --acento: {{ $empresa->color_de_marca }}; }</style>
</head>
<body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased">

    <header class="sticky top-0 z-40 border-b border-gray-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <a href="{{ \App\Support\PortalUrl::inicio($empresa) }}" class="flex items-center gap-2">
                @if ($empresa->logo_url)
                    {{-- Solo el logo, y más grande: el nombre repetido al lado
                         estrechaba la barra y en pantalla angosta se partía en
                         dos líneas encima del menú. El logo ya dice el nombre. --}}
                    <img src="{{ $empresa->logo_url }}" alt="{{ $empresa->nombre }}" class="h-12 w-auto sm:h-14">
                @else
                    <span class="grid h-9 w-9 place-items-center rounded-lg text-sm font-bold text-white" style="background: var(--acento)">
                        {{ $empresa->iniciales }}
                    </span>
                    <span class="text-lg font-bold tracking-tight">{{ $empresa->nombre_comercial ?? $empresa->nombre }}</span>
                @endif
            </a>

            <nav class="flex items-center gap-4 text-sm">
                <a href="{{ \App\Support\PortalUrl::catalogo($empresa) }}" class="font-medium text-gray-600 hover:text-gray-900">
                    Vehículos
                </a>
                <a href="{{ \App\Support\PortalUrl::contacto($empresa) }}" class="font-medium text-gray-600 hover:text-gray-900">
                    Encuéntranos
                </a>
                @if ($empresa->telefono)
                    {{-- Con etiqueta: un número suelto en el menú no se lee
                         como un teléfono al que se puede llamar. --}}
                    <a href="tel:{{ $empresa->telefono }}" class="hidden font-medium text-gray-600 hover:text-gray-900 sm:block">
                        Tel. {{ $empresa->telefono_con_guion }}
                    </a>
                @endif
                <a
                    href="{{ $empresa->whatsapp_enlace ?? '#' }}"
                    target="_blank" rel="noopener"
                    class="rounded-lg px-3 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                    style="background: var(--acento)"
                >WhatsApp</a>
            </nav>
        </div>
    </header>

    <main>
        @yield('contenido')
    </main>

    <footer class="mt-16 border-t border-gray-200 bg-white">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:grid-cols-3 sm:px-6">
            <div>
                @if ($empresa->logo_url)
                    <img src="{{ $empresa->logo_url }}" alt="{{ $empresa->getFilamentName() }}"
                         class="mb-3 h-9 w-auto object-contain">
                @endif

                <p class="text-base font-bold">{{ $empresa->nombre_comercial ?? $empresa->nombre }}</p>
                @if ($empresa->direccion)
                    <p class="mt-2 text-sm text-gray-600">{{ $empresa->direccion }}</p>
                @endif
            </div>

            {{-- Toda la forma de contactar, en un solo lugar.
                 Antes esto mismo salía dos veces: aquí y en un bloque aparte de
                 la página de contacto. El visitante leía lo mismo dos veces y
                 el mantenimiento eran dos sitios que se desincronizaban. --}}
            <div class="text-sm text-gray-600">
                <p class="font-semibold text-gray-900">Contáctanos</p>

                @if ($empresa->whatsapp_enlace)
                    <a href="{{ $empresa->whatsapp_enlace }}" target="_blank" rel="noopener"
                       class="mt-2 block hover:text-gray-900">
                        <span class="font-medium">WhatsApp</span>
                        <span class="block text-xs text-gray-500">Respuesta rápida</span>
                    </a>
                @endif

                @if ($empresa->telefono)
                    <a href="tel:{{ $empresa->telefono }}" class="mt-3 block hover:text-gray-900">
                        <span class="font-medium">+502 {{ $empresa->telefono_con_guion }}</span>
                        <span class="block text-xs text-gray-500">Llámanos</span>
                    </a>
                @endif

                @if ($empresa->email)
                    <a href="mailto:{{ $empresa->email }}" class="mt-3 block break-words hover:text-gray-900">
                        <span class="font-medium">{{ $empresa->email }}</span>
                        <span class="block text-xs text-gray-500">Escríbenos por correo</span>
                    </a>
                @endif
            </div>

            <div class="text-sm text-gray-600">
                <p class="font-semibold text-gray-900">Visítanos</p>
                @foreach ($empresa->sucursales()->where('activa', true)->where('mostrar_en_portal', true)->get() as $sucursal)
                    <p class="mt-2">
                        @if ($sucursal->tieneUbicacion())
                            <a href="{{ $sucursal->mapa_google }}" target="_blank" rel="noopener" class="hover:underline">{{ $sucursal->nombreConLaEmpresa() }}</a>
                        @else
                            {{ $sucursal->nombreConLaEmpresa() }}
                        @endif
                    </p>
                @endforeach

                @if (count($empresa->redes))
                    <p class="mt-5 font-semibold text-gray-900">Síguenos</p>
                    <div class="mt-2 flex flex-wrap gap-3">
                        @foreach ($empresa->redes as $red)
                            <a href="{{ $red['url'] }}" target="_blank" rel="noopener"
                               class="text-sm font-medium text-gray-600 hover:text-gray-900">{{ $red['nombre'] }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="border-t border-gray-100 py-4 text-center text-xs text-gray-400">
            © {{ date('Y') }} {{ $empresa->nombre }} · Hecho con Lotea
        </div>
    </footer>
</body>
</html>
