<?php

return [

    // Qo'llab-quvvatlanadigan tillar (Accept-Language header orqali tanlanadi)
    'locales' => ['uz', 'ru'],

    'otp' => [
        'length' => 6,
        // Kod amal qilish muddati (soniya). TZ: 60-120
        'ttl' => (int) env('OTP_TTL', 120),
        // Noto'g'ri urinishlar soni. TZ: 3-5
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        // Qayta yuborish oralig'i (soniya). TZ: 1 daqiqada 1 marta
        'resend_after' => (int) env('OTP_RESEND_AFTER', 60),
        // Bitta raqamga kunlik SMS limiti
        'daily_limit' => (int) env('OTP_DAILY_LIMIT', 10),
        // Bitta IP dan kunlik SMS limiti
        'ip_daily_limit' => (int) env('OTP_IP_DAILY_LIMIT', 50),
        // Eskiz'da tasdiqlangan shablon bilan mos bo'lishi kerak
        'sms_template' => env('OTP_SMS_TEMPLATE', 'Savdodaftar ilovasiga kirish kodi: {code}'),
        // Local/dev muhitda doimiy kod (production'da ishlamaydi)
        'debug_code' => env('OTP_DEBUG_CODE'),
    ],

    'pin' => [
        'max_attempts' => (int) env('PIN_MAX_ATTEMPTS', 5),
        // Limit tugagach bloklash muddati (soniya)
        'lockout_seconds' => (int) env('PIN_LOCKOUT_SECONDS', 300),
        // SMS orqali tasdiqlangandan keyin PIN'ni yangilash uchun berilgan vaqt (soniya)
        'reset_window' => (int) env('PIN_RESET_WINDOW', 600),
    ],

    'inventory' => [
        // Savdo/chiqimda qoldiq manfiyga tushishiga ruxsat (bozorchi omborni to'liq yuritmasa)
        'allow_negative_stock' => (bool) env('INVENTORY_ALLOW_NEGATIVE_STOCK', false),
        // Mahsulot rasmlari saqlanadigan disk (`php artisan storage:link` kerak)
        'image_disk' => env('PRODUCT_IMAGE_DISK', 'public'),
        // Rasm hajmi limiti (KB)
        'image_max_kb' => (int) env('PRODUCT_IMAGE_MAX_KB', 4096),
    ],

    'billing' => [
        // Tariflar: Standart (savdo + ombor) va Pro (hammasi + AI). Narx — so'm. Muddat hammasi uchun 30 kun (App\Models\Plan::DAYS).
        'plans' => [
            'standard' => [
                'price' => (int) env('STANDARD_PRICE', 12000),
            ],
            'pro' => [
                'price' => (int) env('PRO_PRICE', 49000),
            ],
        ],

        'payme' => [
            'merchant_id' => env('PAYME_MERCHANT_ID'),
            // Webhook Basic auth paroli (Payme kassa kaliti)
            'key' => env('PAYME_KEY'),
            // Payme kabinetidagi "account" maydoni kaliti (order_id | byurtma_id | zakaz_id — uchalasi ham qabul qilinadi)
            'account_field' => env('PAYME_ACCOUNT_FIELD', 'order_id'),
            'checkout_url' => env('PAYME_CHECKOUT_URL', 'https://checkout.paycom.uz'),
        ],

        'click' => [
            'merchant_id' => env('CLICK_MERCHANT_ID'),
            'service_id' => env('CLICK_SERVICE_ID'),
            'secret_key' => env('CLICK_SECRET_KEY'),
            'checkout_url' => env('CLICK_CHECKOUT_URL', 'https://my.click.uz/services/pay'),
        ],
    ],

    // Bepul tarif limiti (Standart limitlari admin panelda — plans jadvali; Pro — cheksiz)
    'limits' => [
        'free_customers' => (int) env('FREE_MAX_CUSTOMERS', 30),
    ],

    // Yangi foydalanuvchiga bepul Standart sinov (kun). 0 — sinov o'chiq.
    'trial' => [
        'days' => (int) env('TRIAL_DAYS', 14),
    ],

    'referral' => [
        // Taklif qilingan foydalanuvchining har bir to'lovidan beriladigan ulush (%)
        'percent' => (float) env('REFERRAL_PERCENT', 10),
        // Ulashiladigan havola; {code} referal kod bilan almashtiriladi
        'link' => env('REFERRAL_LINK', 'https://abdullohinfo.uz/invite/{code}'),
        // Bonusni kartaga yechib olishning minimal summasi (so'm)
        'min_withdrawal' => (int) env('MIN_WITHDRAWAL', 10000),
    ],

    // Admin xabarnomalari: yechib olish so'rovi kelganda Telegram botga yuboriladi (ixtiyoriy)
    // Push-bildirishnomalar: Firebase Cloud Messaging (HTTP v1). Sozlanmasa push yuborilmaydi.
    'push' => [
        'fcm_project_id' => env('FCM_PROJECT_ID'),
        // Firebase service account JSON fayli yo'li (storage/app/firebase.json)
        'fcm_credentials' => env('FCM_CREDENTIALS'),
    ],

    'admin' => [
        'telegram_bot_token' => env('ADMIN_TELEGRAM_BOT_TOKEN'),
        'telegram_chat_id' => env('ADMIN_TELEGRAM_CHAT_ID'),
    ],

    'currency' => [
        'url' => env('CBU_RATES_URL', 'https://cbu.uz/uz/arkhiv-kursov-valyut/json/'),
        // Ko'rsatiladigan valyutalar (tartib shu bo'yicha)
        'codes' => ['USD', 'EUR', 'RUB', 'KZT', 'GBP', 'CNY', 'TRY'],
        // Kesh muddati (soniya); soatlik `rates:refresh` ham yangilab turadi
        'ttl' => (int) env('CBU_RATES_TTL', 3600),
    ],

    // Ochiq sayt: ilovani yuklab olish havolalari (bo'sh bo'lsa tugma yashiriladi)
    'site' => [
        'android_url' => env('SITE_APP_ANDROID_URL'),
        'ios_url' => env('SITE_APP_IOS_URL'),
    ],

    'support' => [
        'phone' => env('SUPPORT_PHONE', '+998900000000'),
        'telegram' => env('SUPPORT_TELEGRAM', 'https://t.me/bozorpro_support'),
        'email' => env('SUPPORT_EMAIL', 'support@abdullohinfo.uz'),
        'working_hours' => env('SUPPORT_HOURS', '09:00 - 18:00'),
    ],

    // Qo'llanma videolari (url — YouTube yoki boshqa video havola). Kodni o'zgartirmasdan
    // yangilash uchun GUIDES_JSON env'ga JSON massiv berish mumkin.
    'guides' => json_decode((string) env('GUIDES_JSON', ''), true) ?: [
        ['id' => 'start', 'title_uz' => 'Ilovadan foydalanishni boshlash', 'title_ru' => 'Начало работы с приложением', 'url' => 'https://www.youtube.com/@bozorpro'],
        ['id' => 'debts', 'title_uz' => 'Qarz daftari bilan ishlash', 'title_ru' => 'Работа с долговой тетрадью', 'url' => 'https://www.youtube.com/@bozorpro'],
        ['id' => 'sales', 'title_uz' => 'Savdo va ombor', 'title_ru' => 'Продажи и склад', 'url' => 'https://www.youtube.com/@bozorpro'],
        ['id' => 'pro', 'title_uz' => 'Tariflar va to\'lov', 'title_ru' => 'Тарифы и оплата', 'url' => 'https://www.youtube.com/@bozorpro'],
    ],

    'backup' => [
        // Zaxira fayllari saqlanadigan disk (production'da s3 tavsiya etiladi)
        'disk' => env('BACKUP_DISK', 'local'),
        // Har bir foydalanuvchi uchun saqlanadigan oxirgi zaxiralar soni
        'keep' => (int) env('BACKUP_KEEP', 10),
    ],

];
