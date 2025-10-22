<?php

return [
    // URL de base de l'API
    'api_url' => env('MOBILE_MONEY_API_URL', 'https://gateway.example.com/api'),

    // Vos credentials API
    'api_key' => env('MOBILE_MONEY_API_KEY'),
    'secret_key' => env('MOBILE_MONEY_SECRET_KEY'),
    'app_id' => env('MOBILE_MONEY_APP_ID'),

    // URL de callback par défaut
    'callback_url' => env('MOBILE_MONEY_CALLBACK_URL', null),

    // Timeout des requêtes (en secondes)
    'timeout' => env('MOBILE_MONEY_TIMEOUT', 30),
];
