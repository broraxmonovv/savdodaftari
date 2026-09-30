<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tarif (standard | pro): narx, muddat va faollik admin panelda boshqariladi */
class Plan extends Model
{
    protected $fillable = ['key', 'price', 'days', 'is_active', 'sort', 'features'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'days' => 'integer',
            'is_active' => 'boolean',
            'features' => 'array',
        ];
    }
}
