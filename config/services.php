<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    /**
     * Lectura de documentos con IA (tarjeta de circulación, título americano,
     * hoja de subasta). La llave es del cliente y vive solo en el .env.
     */
    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'modelo' => env('OPENROUTER_MODELO', 'qwen/qwen2.5-vl-72b-instruct'),
        'url' => env('OPENROUTER_URL', 'https://openrouter.ai/api/v1/chat/completions'),

        // Precio publicado por OpenRouter, por millón de tokens. Cambia con el
        // tiempo: actualizalo aquí y el cálculo de costo se ajusta solo.
        'precio_entrada' => env('OPENROUTER_PRECIO_ENTRADA', 0.25),
        'precio_salida' => env('OPENROUTER_PRECIO_SALIDA', 0.75),
        'tipo_cambio' => env('OPENROUTER_TIPO_CAMBIO', 7.70),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
