<?php

return [

    'sms' => [
        // log | array | eskiz
        'driver' => env('SMS_DRIVER', 'log'),

        'eskiz' => [
            'base_url' => env('ESKIZ_BASE_URL', 'https://notify.eskiz.uz'),
            'email' => env('ESKIZ_EMAIL'),
            'password' => env('ESKIZ_PASSWORD'),
            // Eskiz'da ro'yxatdan o'tgan sender name
            'from' => env('ESKIZ_FROM', '4546'),
        ],
    ],

    // Claude API (Pro: eski daftar OCR importi va AI biznes yordamchi). Kalit yo'q bo'lsa bu funksiyalar o'chiq.
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5-5'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
    ],

];
