<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tarif (standard | pro): narx, muddat va faollik admin panelda boshqariladi */
class Plan extends Model
{
    /** Barcha pullik tariflarning muddati qat'iy 30 kun (admin panelda o'zgartirilmaydi) */
    public const DAYS = 30;

    protected $fillable = ['key', 'price', 'days', 'max_customers', 'max_products', 'is_active', 'sort', 'features'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'days' => 'integer',
            'max_customers' => 'integer',
            'max_products' => 'integer',
            'is_active' => 'boolean',
            'features' => 'array',
        ];
    }

    /**
     * Bazada yetishmayotgan tarif qatorlarini (standard/pro) `config` dagi qiymatlar bilan yaratadi.
     * Tarif jadvali bo'sh qolgan bo'lsa (masalan, migratsiya boshqa muhitda ishga tushgan) checkout
     * "tarif mavjud emas" xatosi bilan to'xtab qolmaydi. Mavjud qatorlarga tegmaydi.
     */
    public static function ensureDefaults(): void
    {
        $defaults = [
            'standard' => ['sort' => 1, 'features' => ['sales', 'inventory']],
            'pro' => ['sort' => 2, 'features' => ['sales', 'inventory', 'voice', 'ai_assistant', 'ocr_import', 'advanced_reports']],
        ];

        foreach ($defaults as $key => $meta) {
            static::firstOrCreate(['key' => $key], [
                'price' => (int) config("savdodaftar.billing.plans.{$key}.price"),
                'days' => self::DAYS,
                'is_active' => true,
                'sort' => $meta['sort'],
                'features' => $meta['features'],
            ]);
        }
    }
}
