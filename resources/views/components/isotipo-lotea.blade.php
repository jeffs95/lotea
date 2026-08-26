{{--
    El isotipo de Lotea, dibujado y no cargado.

    Va en SVG en lugar de un archivo porque esta marca aparece en la primera
    pantalla del sistema: si dependiera del FTP o del almacenamiento, un fallo
    allá dejaría el acceso sin identidad. Además se ve nítido a cualquier tamaño
    y no cuesta una petición.

    La «L» lleva el brazo biselado hacia adelante: es un patio de carros, algo
    tiene que moverse.
--}}
@props([
    'color' => '#f59e0b',
    'sobreFondo' => false,
])

<svg
    {{ $attributes->class(['shrink-0']) }}
    viewBox="0 0 48 48"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    role="img"
    aria-label="Lotea"
>
    @unless ($sobreFondo)
        <rect width="48" height="48" rx="13" fill="{{ $color }}" />
    @endunless

    <path
        d="M15 12h7.4v16.1h11.9l-5.7 7.4H15z"
        fill="{{ $sobreFondo ? $color : '#ffffff' }}"
    />
</svg>
