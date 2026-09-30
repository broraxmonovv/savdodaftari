<?php

return [
    'customer' => [
        'created' => 'Mijoz qo\'shildi.',
        'updated' => 'Mijoz ma\'lumotlari saqlandi.',
        'deleted' => 'Mijoz o\'chirildi.',
        'has_debt' => 'Qarzi bo\'lgan mijozni o\'chirib bo\'lmaydi. Avval qarzni yoping.',
    ],

    'debt' => [
        'created' => 'Qarz yozildi.',
        'updated' => 'Qarz ma\'lumotlari saqlandi.',
        'deleted' => 'Qarz o\'chirildi.',
        'payment_recorded' => 'To\'lov qabul qilindi. Qolgan qarz: :remaining.',
        'payment_exceeds' => 'To\'lov summasi qarzdan ko\'p bo\'lishi mumkin emas. Qolgan qarz: :remaining.',
        'no_open_debts' => 'Bu mijozda ochiq qarz yo\'q.',
        'has_payments' => 'To\'lovi bo\'lgan qarzni o\'chirib bo\'lmaydi.',
    ],

    'product' => [
        'created' => 'Mahsulot qo\'shildi.',
        'updated' => 'Mahsulot ma\'lumotlari saqlandi.',
        'deleted' => 'Mahsulot o\'chirildi.',
        'barcode_taken' => 'Bu barcode ":name" mahsulotiga biriktirilgan.',
        'not_found_by_barcode' => 'Bu barcode bo\'yicha mahsulot topilmadi.',
        'not_found' => 'Mahsulot topilmadi.',
        'image_saved' => 'Rasm saqlandi.',
        'image_deleted' => 'Rasm o\'chirildi.',
    ],

    'stock' => [
        'initial_note' => 'Boshlang\'ich qoldiq',
        'in' => 'Kirim saqlandi. Yangi qoldiq: :stock.',
        'out' => 'Chiqim saqlandi. Yangi qoldiq: :stock.',
        'adjusted' => 'Qoldiq tuzatildi. Farq: :diff.',
        'no_change' => 'Qoldiq o\'zgarmadi.',
        'insufficient' => '":product" omborda yetarli emas. Mavjud: :available.',
        'inventory_saved' => 'Inventarizatsiya yakunlandi. :count ta mahsulot qoldig\'i tuzatildi.',
    ],

    'sale' => [
        'created' => 'Savdo yakunlandi. Jami: :total.',
        'returned' => 'Qaytarish saqlandi. Summa: :total.',
        'discount_exceeds' => 'Chegirma savdo summasidan ko\'p bo\'lishi mumkin emas.',
        'payment_mismatch' => 'To\'lovlar yig\'indisi (:paid) savdo summasiga (:total) teng bo\'lishi kerak.',
        'customer_required' => 'Qarzga savdo uchun mijozni tanlang.',
        'already_returned' => 'Bu savdo to\'liq qaytarilgan.',
        'nothing_to_return' => 'Qaytarish uchun mahsulot yo\'q.',
        'return_exceeds' => '":product" bo\'yicha qaytarish miqdori sotilgandan ko\'p. Qaytarish mumkin: :available.',
        'refund_debt_invalid' => 'Qarzdan ayirish mumkin emas. Qolgan qarz: :remaining.',
        'debt_note' => 'Savdo #:id',
        'movement_note' => 'Savdo #:id',
        'return_movement_note' => 'Savdo #:id qaytarildi',
        'return_debt_note' => 'Savdo #:id qaytarildi',
        'methods' => [
            'cash' => 'Naqd',
            'card' => 'Karta',
            'debt' => 'Qarz',
            'mixed' => 'Aralash',
        ],
        'receipt' => [
            'subtotal' => 'Summa',
            'discount' => 'Chegirma',
            'total' => 'Jami',
            'payment' => 'To\'lov',
            'debt' => 'Qarzga',
            'customer' => 'Mijoz',
            'returned' => 'Qaytarildi',
        ],
    ],

    'expense' => [
        'created' => 'Xarajat yozildi.',
        'deleted' => 'Xarajat o\'chirildi.',
    ],

    'account_blocked' => 'Hisobingiz bloklangan. Qo\'llab-quvvatlashga murojaat qiling.',

    'billing' => [
        'already_pro' => 'Pro tarif allaqachon faol.',
        'plan_unavailable' => 'Bu tarif hozircha sotuvda emas.',
        'already_standard' => 'Standart tarif allaqachon faol.',
        'plan_required' => 'Bu bo\'lim uchun :plan tarifni faollashtiring.',
        'checkout_created' => 'To\'lov yaratildi. To\'lov sahifasiga o\'ting.',
    ],

    'limits' => [
        'customers' => 'Mijozlar soni limitiga yetdingiz (:limit ta). Ko\'proq qo\'shish uchun :plan tarifiga o\'ting.',
        'products' => 'Mahsulotlar soni limitiga yetdingiz (:limit ta). Ko\'proq qo\'shish uchun :plan tarifiga o\'ting.',
    ],

    'ai' => [
        'not_configured' => 'AI xizmati hali sozlanmagan.',
        'failed' => 'AI xizmati javob bermadi. Keyinroq qayta urinib ko\'ring.',
        'nothing_to_import' => 'Import qilinadigan qator yo\'q.',
        'ocr_imported' => 'Daftardagi ma\'lumotlar qo\'shildi.',
        'ocr_customer_note' => 'Eski daftardan ko\'chirilgan',
        'ocr_debt_note' => 'Eski daftardan',
        'unknown' => 'Buyruqni tushunmadim. Masalan: "Ali akaga 150 ming qarz yoz".',
        'debt_add' => ':name ga :amount qarz yozilsinmi?',
        'debt_payment' => ':name :amount qarzini qaytardi — qabul qilinsinmi?',
        'stock_in' => ':product — :qty :unit kirim qilinsinmi?',
        'product_not_found' => '":name" nomli mahsulot topilmadi.',
        'customer_not_found' => '":name" nomli mijoz topilmadi.',
        'choose_customer' => '":name" bo\'yicha bir nechta mijoz topildi — birini tanlang.',
        'need_amount' => 'Summani aniq ayting. Masalan: "150 ming".',
        'sales' => 'Bugun :count ta savdo, jami :total, yalpi foyda :profit.',
        'profit' => 'Bugungi yalpi foyda :profit, xarajat :expenses, sof foyda :net.',
        'overdue' => 'Muddati o\'tgan qarzlar: :count ta, jami :total.',
        'no_overdue' => 'Muddati o\'tgan qarz yo\'q.',
        'low_stock' => ':count ta mahsulot kam qolgan.',
        'no_low_stock' => 'Kam qolgan mahsulot yo\'q.',
        'currency' => 'so\'m',
    ],

    'push' => [
        'subscription_title' => 'Tarif tugamoqda',
        'subscription_body' => 'Sizning :plan tarifingiz :date kuni tugaydi. Uzluksiz foydalanish uchun uzaytiring.',
        'low_stock_title' => 'Mahsulot kam qoldi',
        'low_stock_body' => ':count ta mahsulot minimal qoldiqdan past.',
        'debts_title' => 'Muddati o\'tgan qarzlar',
        'debts_body' => ':count ta qarzning muddati o\'tgan.',
    ],

    'bonus' => [
        'insufficient' => 'Bonus balansi yetarli emas.',
        'min_withdrawal' => 'Yechib olish uchun kamida :amount so\'m kerak.',
        'already_processed' => 'Bu so\'rov allaqachon ko\'rib chiqilgan.',
        'withdrawal_sent' => 'So\'rov adminga yuborildi. Admin pulni kartangizga o\'tkazadi.',
        'plan_paid' => 'Tarif bonus balansidan to\'landi va faollashtirildi.',
        'marked_paid' => 'So\'rov to\'langan deb belgilandi.',
        'rejected' => 'So\'rov rad etildi, summa balansga qaytarildi.',
        'card_invalid' => 'Karta raqami noto\'g\'ri.',
        'admin_only' => 'Bu amal faqat admin uchun.',
    ],

    'referral' => [
        'invalid' => 'Referal kod topilmadi.',
        'self' => 'O\'zingizning kodingizni kiritib bo\'lmaydi.',
        'already_attached' => 'Referal kod allaqachon kiritilgan.',
    ],

    'backup' => [
        'restored' => 'Ma\'lumotlar zaxiradagi holatga qaytarildi.',
        'corrupted' => 'Zaxira fayli buzilgan.',
        'invalid' => 'Zaxira fayli yaroqsiz.',
        'created' => 'Zaxira nusxa yaratildi.',
        'deleted' => 'Zaxira nusxa o\'chirildi.',
        'file_missing' => 'Zaxira fayli topilmadi.',
    ],
];
