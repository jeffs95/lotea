<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * Los archivos del sistema: fotos de las unidades y sus documentos.
         *
         * Sin valores por defecto a propósito. Un host y una contraseña
         * escritos aquí quedan en el repositorio para siempre; si falta la
         * configuración es mejor que la subida falle con un error claro que
         * que se conecte a un servidor que nadie declaró.
         *
         * `throw` en true: si el FTP no responde, hay que enterarse al subir el
         * archivo, no descubrirlo después con la ficha del carro sin fotos.
         */
        'ftp_documentos' => [
            'driver' => 'ftp',
            'host' => env('FTP_HOST'),
            'username' => env('FTP_USERNAME'),
            'password' => env('FTP_PASSWORD'),
            'port' => (int) env('FTP_PORT', 21),
            'root' => env('FTP_ROOT', '/'),
            'passive' => (bool) env('FTP_PASSIVE', true),
            'ssl' => (bool) env('FTP_SSL', false),
            'timeout' => (int) env('FTP_TIMEOUT', 30),
            'throw' => true,
        ],

        /*
         * Copia local de lo que ya se leyó del FTP.
         *
         * El portal público muestra decenas de fotos por visita; pedirlas al
         * FTP cada vez lo pondría de rodillas y haría el catálogo lento. Esto
         * no es la fuente de verdad: se puede borrar entero y se vuelve a
         * llenar solo.
         */
        'cache_archivos' => [
            'driver' => 'local',
            'root' => storage_path('app/cache-archivos'),
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
