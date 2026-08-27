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
        /*
         * Cloudflare R2, que habla el mismo protocolo que S3.
         *
         * Van dos y no uno a propósito. Al público se le conecta un dominio
         * para que el CDN sirva las fotos, y eso hace accesible todo lo que
         * tenga dentro: los títulos de vehículo y las tarjetas de circulación
         * no pueden estar ahí. El privado no se expone nunca y sus archivos
         * salen con enlaces firmados que caducan.
         *
         * La región es «auto»: R2 no tiene regiones como S3, pero el SDK exige
         * una y firma con ella.
         */
        'r2_publico' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('R2_BUCKET_PUBLICO'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => true,
            // El dominio propio por el que el navegador pide las fotos. Sin él
            // no hay CDN y las URL apuntarían al endpoint de Cloudflare.
            'url' => env('R2_URL_PUBLICA'),
            'visibility' => 'public',
            /*
             * La cabecera con la que R2 va a entregar cada archivo.
             *
             * Sin un «public» explícito, Cloudflare responde DYNAMIC y no
             * guarda nada en el borde: la foto viaja desde el cubo en cada
             * visita y se pierde justo lo que se venía a ganar.
             *
             * Un año e «immutable» porque estos archivos no cambian nunca: si
             * se sube otra foto es otro archivo con otro nombre, así que el
             * navegador no tiene ni que preguntar si sigue vigente.
             */
            'options' => [
                'CacheControl' => 'public, max-age=31536000, immutable',
            ],
            'throw' => true,
        ],

        'r2_privado' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('R2_BUCKET_PRIVADO'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw' => true,
        ],

        'ftp_documentos' => [
            'driver' => 'ftp',
            'host' => env('FTP_HOST'),
            'username' => env('FTP_USERNAME'),
            'password' => env('FTP_PASSWORD'),
            'port' => (int) env('FTP_PORT', 21),
            'root' => env('FTP_ROOT', '/SAS-LOTEA'),
            'passive' => (bool) env('FTP_PASSIVE', true),
            'ssl' => (bool) env('FTP_SSL', false),
            'timeout' => (int) env('FTP_TIMEOUT', 30),
            'throw' => true,
        ],

        /*
         * El mismo disco con el nombre en inglés.
         *
         * Los otros sistemas de la casa llaman «ftp_documents» a su disco, y
         * poner ese nombre aquí no da un error al arrancar: falla mucho después,
         * cuando alguien sube una foto, con un «does not have a configured
         * driver» que no dice qué hacer. Este alias evita el viaje.
         */
        'ftp_documents' => [
            'driver' => 'ftp',
            'host' => env('FTP_HOST'),
            'username' => env('FTP_USERNAME'),
            'password' => env('FTP_PASSWORD'),
            'port' => (int) env('FTP_PORT', 21),
            'root' => env('FTP_ROOT', '/SAS-LOTEA'),
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
