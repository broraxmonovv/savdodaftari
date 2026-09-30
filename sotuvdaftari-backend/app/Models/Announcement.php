<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Admin yuboradigan bildirishnoma: hammaga, bitta foydalanuvchiga yoki tarif bo'yicha */
class Announcement extends Model
{
    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_USER = 'user';

    public const AUDIENCE_PLAN = 'plan';

    protected $fillable = ['title', 'body', 'audience', 'user_id', 'plan', 'created_by'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Berilgan foydalanuvchiga ko'rinadigan e'lonlar */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $plan = $user->currentPlan();

        return $query->where(function (Builder $q) use ($user, $plan) {
            $q->where('audience', self::AUDIENCE_ALL)
                ->orWhere(fn (Builder $w) => $w->where('audience', self::AUDIENCE_USER)->where('user_id', $user->id))
                ->orWhere(fn (Builder $w) => $w->where('audience', self::AUDIENCE_PLAN)->where('plan', $plan));
        });
    }
}
