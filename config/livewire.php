<?php

use App\Support\LimiteDeSubida;

/*
|--------------------------------------------------------------------------
| Solo lo que hace falta cambiarle a Livewire
|--------------------------------------------------------------------------
|
| El resto se queda como viene del paquete. Ojo: el mezclado de configuración
| de Laravel es de primer nivel, así que este array «temporary_file_upload»
| reemplaza entero al suyo — por eso están todas sus claves y no solo la que
| cambia.
*/

return [
    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),

        /*
         * El tope de fábrica son 12 MB, por debajo de lo que aceptan nuestros
         * formularios. Quien subía una foto más grande veía «no se pudo subir»
         * y nada más: el archivo moría en Livewire antes de llegar a Laravel,
         * así que no había ni error en el log ni forma de adivinar el motivo.
         */
        'rules' => ['required', 'file', 'max:'.LimiteDeSubida::KILOBYTES],

        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],
];
