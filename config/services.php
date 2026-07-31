<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'microsoft' => [
        // 'delegated'   => cada usuario conecta su cuenta (permiso Calendars.Read delegado)
        // 'application' => la app lee buzones por correo (permiso Calendars.Read de aplicación)
        'mode' => env('MS_MODE', 'delegated'),
        'client_id' => env('MS_CLIENT_ID'),
        'client_secret' => env('MS_CLIENT_SECRET'),
        'tenant' => env('MS_TENANT_ID'),
        'redirect' => env('MS_REDIRECT_URI'),
        // Nombre visible del calendario dedicado que la app crea al vincular
        // una cuenta. El id resultante se guarda por cuenta, no aquí: los ids
        // de calendario de Graph pertenecen a un buzón concreto.
        'calendar_name' => env('MS_CALENDAR_NAME', 'Eventos SGC'),
        // Restringe el SSO a un dominio de correo. Vacío = cualquiera del tenant.
        'allowed_domain' => env('MS_ALLOWED_DOMAIN'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
