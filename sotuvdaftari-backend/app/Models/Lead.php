<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_DONE = 'done';

    public const STATUS_SPAM = 'spam';

    public const STATUSES = [self::STATUS_NEW, self::STATUS_CONTACTED, self::STATUS_DONE, self::STATUS_SPAM];

    /** Formadagi savdo turlari (kalit -> uz nomi) */
    public const BUSINESS_TYPES = ['shop', 'market', 'wholesale', 'service', 'other'];

    protected $fillable = [
        'name', 'phone', 'business_type', 'message', 'source', 'status',
        'admin_note', 'locale', 'ip', 'user_agent', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }
}
