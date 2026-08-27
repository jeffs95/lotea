<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dónde viven los archivos
    |--------------------------------------------------------------------------
    |
    | Se separan en dos porque no todos los archivos son igual de sensibles.
    |
    | En «publico» van las fotos del catálogo: se sirven por CDN y cualquiera
    | con el enlace las ve, que es justo lo que se quiere de una foto que va a
    | salir publicada.
    |
    | En «privado» van los documentos —títulos, tarjetas de circulación, que
    | llevan datos del propietario— y las fotos de subasta, que muestran el
    | carro como llegó: golpeado, antes del taller. Eso último no es un secreto
    | legal pero sí comercial, y el concesionario no quiere que su comprador lo
    | vea. De ahí salen solo con enlaces firmados que caducan.
    |
    | Si las dos apuntan al mismo disco —como en desarrollo y en los tests—
    | todo sigue funcionando: la distinción la hace el código, no el disco.
    |
    */

    'discos' => [
        // Sin definir, los dos caen al disco de medialibrary. Así en desarrollo
        // y en los tests hay uno solo, y quien cambia ese disco sobre la marcha
        // —los tests lo hacen— no se encuentra con que estos dos se quedaron
        // apuntando a otro sitio.
        'publico' => env('LOTEA_DISCO_PUBLICO'),
        'privado' => env('LOTEA_DISCO_PRIVADO'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cuánto vale un enlace firmado
    |--------------------------------------------------------------------------
    |
    | Lo suficiente para abrir el documento y leerlo, no para reenviarlo por
    | WhatsApp y que siga sirviendo mañana.
    |
    */

    'minutos_de_enlace_firmado' => (int) env('LOTEA_MINUTOS_ENLACE_FIRMADO', 15),

];
