<?php

return [
    'gemini' => [
        'key'   => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],
    'fonnte' => [
        'token'      => env('FONNTE_TOKEN'),
        'api_url'    => env('FONNTE_API_URL', 'https://api.fonnte.com/send'),
        'bot_number' => env('FONNTE_BOT_NUMBER'),
    ],
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],
    'n8n' => [
        'webhook_secret' => env('N8N_WEBHOOK_SECRET'),
        'base_url'       => env('N8N_BASE_URL'),
    ],
];
