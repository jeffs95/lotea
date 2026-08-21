{{--
    La paleta del concesionario, emitida en cada render.

    No se usa ->colors() del panel: Filament cachea esa paleta la primera vez
    que la pide, así que en un proceso que atiende a dos clientes seguidos
    (Octane) el segundo vería el color del primero. Esto se evalúa por render y
    va después de los estilos de Filament, así que gana por orden de cascada.
--}}
<style>
    :root {
        @foreach ($paleta as $tono => $valor)
            --primary-{{ $tono }}: {{ $valor }};
        @endforeach
    }
</style>
