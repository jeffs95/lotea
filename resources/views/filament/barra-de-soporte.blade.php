{{--
    Aviso permanente de que se está dentro del panel de un cliente.

    Va arriba de todo y en un color que no se parece a nada del sistema: el
    riesgo real no es entrar, es olvidarse de que se entró y creer que se está
    en el panel propio mientras se cambian datos ajenos.
--}}
@php($empresa = \App\Support\ModoSoporte::empresa())

@if ($empresa)
    <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-amber-500 px-4 py-2 text-center text-sm font-medium text-amber-950">
        <span>
            Estás dentro del panel de <strong>{{ $empresa->getFilamentName() }}</strong>
            para dar soporte. Lo que cambies queda a tu nombre en su historial.
        </span>

        <a href="{{ route('soporte.salir') }}"
           class="rounded-md bg-amber-950/15 px-2.5 py-1 font-semibold transition hover:bg-amber-950/25">
            Salir del soporte
        </a>
    </div>
@endif
