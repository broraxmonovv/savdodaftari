<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use BelongsToUser;

    public const PLAN_FREE = 'free';

    public const PLAN_STANDARD = 'standard';

    public const PLAN_PRO = 'pro';

    /** To'lov orqali sotib olinadigan tariflar */
    public const PAID_PLANS = [self::PLAN_STANDARD, self::PLAN_PRO];

    /** Tarif darajasi: yuqori raqam — ko'proq imkoniyat */
    public const PLAN_LEVELS = [self::PLAN_FREE => 0, self::PLAN_STANDARD => 1, self::PLAN_PRO => 2];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'user_id',
        'plan',
        'status',
        'started_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->expires_at->isFuture();
    }

    /** Faol va muddati tugamagan obunalar */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)->where('expires_at', '>', now());
    }
}
