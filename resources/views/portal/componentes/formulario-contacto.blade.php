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
        <p class="font-semibold text-green-800">¡Listo, ya te escribimos!</p>
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

        <input type="text" name="nombre" placeholder="Tu nombre" required maxlength="120"
               value="{{ old('nombre') }}"
               class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
        @error('nombre')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

        <input type="tel" name="telefono" placeholder="Teléfono" required maxlength="30"
               value="{{ old('telefono') }}"
               class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
        @error('telefono')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

        <input type="email" name="email" placeholder="Correo (opcional)" maxlength="120"
               value="{{ old('email') }}"
               class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">

        <textarea name="mensaje" rows="3" maxlength="1000" placeholder="¿Algo que quieras preguntar?"
                  class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">{{ old('mensaje') }}</textarea>

        <button type="submit" class="w-full rounded-xl px-4 py-3 font-semibold text-white transition hover:opacity-90" style="background: var(--acento)">
            Que me contacten
        </button>
    </form>
@endif
