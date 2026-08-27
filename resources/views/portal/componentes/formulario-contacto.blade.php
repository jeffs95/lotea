{{--
    El formulario que recoge prospectos. Se usa en la ficha del vehículo y en
    la página de contacto, así que vive aquí: la trampa para bots y el sello de
    tiempo no se pueden quedar a medias en una de las dos copias.

    @param  \App\Models\Unidad|null  $unidad  si viene, el prospecto queda
            atado a ese carro y el vendedor sabe por cuál preguntaron.
--}}
@php($unidad = $unidad ?? null)
@php($titulo = $titulo ?? null)

@if (session('lead_enviado'))
    <div class="rounded-xl bg-green-50 p-4 text-center ring-1 ring-green-200">
        <p class="font-semibold text-green-800">Recibimos tus datos</p>
        <p class="mt-1 text-sm text-green-700">Un asesor te contacta hoy mismo.</p>
    </div>
@else
    @if ($titulo)
        <h2 class="text-base font-bold">{{ $titulo }}</h2>
    @endif

    <form method="POST" action="{{ \App\Support\PortalUrl::ruta('lead', $empresa) }}" class="mt-4 space-y-3">
        @csrf

        @if ($unidad)
            <input type="hidden" name="unidad_id" value="{{ $unidad->id }}">
        @endif

        {{-- Trampa para bots: invisible y sin autocompletar.
             Una persona nunca lo ve; un script lo llena. --}}
        <input type="text" name="{{ \App\Http\Controllers\Portal\LeadController::HONEYPOT }}"
               tabindex="-1" autocomplete="off" aria-hidden="true"
               style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
        <input type="hidden" name="_t" value="{{ now()->timestamp }}">

        {{-- El navegador avisa antes de mandar: el visitante corrige en el
             momento en vez de recargar la página y volver a escribir todo. --}}
        <input type="text" name="nombre" placeholder="Nombre completo" required
               minlength="3" maxlength="120"
               autocomplete="name" autocapitalize="words"
               value="{{ old('nombre') }}"
               class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
        @error('nombre')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

        {{-- inputmode abre el teclado numérico en el teléfono, que es desde
             donde entra casi todo el tráfico. El patrón deja pasar los formatos
             que la gente escribe de verdad, con guiones, espacios o el +502. --}}
        <input type="tel" name="telefono" placeholder="Número de teléfono (5555-1234)" required
               inputmode="tel" autocomplete="tel"
               pattern="[+]?[0-9()\s\-]{8,20}"
               title="Ocho dígitos si es de Guatemala. Puede escribirlo con guiones o con el +502."
               maxlength="30"
               value="{{ old('telefono') }}"
               class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
        @error('telefono')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

        <input type="email" name="email" placeholder="Correo electrónico (opcional)" maxlength="120"
               inputmode="email" autocomplete="email" autocapitalize="off" spellcheck="false"
               value="{{ old('email') }}"
               class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
        @error('email')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

        <textarea name="mensaje" rows="3" maxlength="1000" placeholder="¿En qué podemos ayudarte?"
                  class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">{{ old('mensaje') }}</textarea>

        <button type="submit" class="w-full rounded-xl px-4 py-3 font-semibold text-white transition hover:opacity-90" style="background: var(--acento)">
            Solicitar información
        </button>
    </form>
@endif
