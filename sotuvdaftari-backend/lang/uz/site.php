<?php

return [
    'brand' => 'BozorPro',
    'nav' => ['features' => 'Imkoniyatlar', 'plans' => 'Tariflar', 'faq' => 'Savol-javob', 'contact' => 'Ariza qoldirish'],
    'hero' => [
        'title' => 'Bozorchi uchun telefon ichidagi aqlli daftar',
        'subtitle' => 'Qarz, mijoz, savdo, ombor va xarajatlarni qog\'oz daftarsiz, 2–3 bosishda yuriting. Foyda va qarzlaringiz doim ko\'z oldingizda.',
        'cta_download' => 'Ilovani yuklab olish',
        'cta_request' => 'Ariza qoldirish',
        'android' => 'Android uchun',
        'ios' => 'iPhone uchun',
    ],
    'features' => [
        'title' => 'Nimalar qila oladi',
        'items' => [
            ['icon' => '📒', 'title' => 'Qarz daftari', 'text' => 'Kim qancha qarz, qachon qaytaradi — qisman to\'lovlar va muddat eslatmalari bilan.'],
            ['icon' => '🛒', 'title' => 'Savdo', 'text' => 'Mahsulotni tanlang, miqdorni kiriting — chek va foyda avtomatik hisoblanadi.'],
            ['icon' => '📦', 'title' => 'Ombor', 'text' => 'Qoldiq, tannarx va kam qolgan mahsulot ogohlantirishi. Barcode bilan tez qidirish.'],
            ['icon' => '📊', 'title' => 'Hisobot', 'text' => 'Kunlik, haftalik, oylik savdo, xarajat va sof foyda.'],
            ['icon' => '🎙️', 'title' => 'Ovozli boshqaruv va AI', 'text' => '"Ali akaga 150 ming qarz yoz" deb ayting — ilova o\'zi yozadi (Pro).'],
            ['icon' => '💱', 'title' => 'Kurslar va kalkulyator', 'text' => 'Markaziy bank kurslari va har sahifada turadigan kalkulyator.'],
        ],
    ],
    'steps' => [
        'title' => 'Qanday boshlanadi',
        'items' => [
            ['title' => 'Yuklab oling', 'text' => 'Ilovani telefoningizga o\'rnating.'],
            ['title' => 'Telefon raqam bilan kiring', 'text' => 'SMS kod orqali 1 daqiqada ro\'yxatdan o\'ting.'],
            ['title' => 'Yuritishni boshlang', 'text' => 'Mijoz va qarzlarni kiriting yoki eski daftaringizni ko\'chiring.'],
        ],
    ],
    'plans' => [
        'title' => 'Tariflar',
        'subtitle' => 'Payme yoki Click orqali oson to\'lov. Istalgan vaqt uzaytirish mumkin.',
        'free' => 'Bepul',
        'standard' => 'Standart',
        'pro' => 'Pro',
        'per' => ':days kun',
        'currency' => 'so\'m',
        'free_features' => ['Mijozlar va qarz daftari', 'Xarajatlar', 'Bugungi va 7 kunlik hisobot'],
        'standard_features' => ['Bepuldagi hamma narsa', 'Savdo bo\'limi', 'Ombor bo\'limi'],
        'pro_features' => ['Standartdagi hamma narsa', 'Ovozli boshqaruv va AI yordamchi', 'Eski daftar OCR importi', 'Kengaytirilgan hisobot va backup'],
    ],
    'referral' => [
        'title' => 'Do\'st taklif qiling — bonus oling',
        'text' => 'Taklif qilgan do\'stingiz qilgan har bir to\'lovdan :percent% bonus olasiz. Bonusni tarifga to\'lash yoki kartaga yechib olish mumkin.',
    ],
    'faq' => [
        'title' => 'Ko\'p so\'raladigan savollar',
        'items' => [
            ['q' => 'Internet bo\'lmasa ishlaydimi?', 'a' => 'Asosiy ma\'lumotlarni ko\'rish va kiritish mumkin, internet qaytganda sinxronlanadi.'],
            ['q' => 'Ma\'lumotlarim xavfsizmi?', 'a' => 'Kirish PIN kod va SMS orqali himoyalangan, ma\'lumotlar serverda zaxiralanadi.'],
            ['q' => 'Qanday to\'layman?', 'a' => 'Payme yoki Click orqali, yoki to\'plangan bonus hisobidan.'],
            ['q' => 'Eski daftarimni ko\'chira olamanmi?', 'a' => 'Ha, Pro tarifda daftar rasmidan mijoz va qarzlar avtomatik ajratiladi.'],
        ],
    ],
    'form' => [
        'title' => 'Ariza qoldiring',
        'subtitle' => 'Ma\'lumotlaringizni qoldiring — biz siz bilan bog\'lanib, ilovadan foydalanishda yordam beramiz.',
        'name' => 'Ismingiz',
        'phone' => 'Telefon raqamingiz',
        'phone_hint' => '+998 90 123 45 67',
        'business' => 'Savdo turi',
        'business_options' => ['shop' => 'Do\'kon', 'market' => 'Bozor / rasta', 'wholesale' => 'Ulgurji savdo', 'service' => 'Xizmat ko\'rsatish', 'other' => 'Boshqa'],
        'message' => 'Izoh (ixtiyoriy)',
        'submit' => 'Yuborish',
        'success' => 'Rahmat! Arizangiz qabul qilindi, tez orada bog\'lanamiz.',
        'consent' => 'Ma\'lumotlarim bog\'lanish maqsadida qayta ishlanishiga roziman.',
        'invalid_phone' => 'Telefon raqam noto\'g\'ri. Namuna: +998901234567',
    ],
    'footer' => ['contact' => 'Aloqa', 'hours' => 'Ish vaqti', 'rights' => 'Barcha huquqlar himoyalangan.'],
];
