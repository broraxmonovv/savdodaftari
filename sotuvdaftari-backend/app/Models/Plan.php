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
}
