<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'referral_code',
        'referred_by_id',
        'pin',
        'shop_name',
        'business_type',
        'locale',
        'sms_reminders',
        'phone_verified_at',
        'last_login_at',
    ];

    // is_admin mass-assignment orqali o'zgartirilmaydi (faqat `admin:grant` buyrug'i)

    protected $hidden = [
        'pin',
        'admin_password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'pin' => 'hashed',
            'bonus_balance' => 'decimal:2',
            'is_admin' => 'boolean',
            'sms_reminders' => 'boolean',
            'trial_started_at' => 'datetime',
            'blocked_at' => 'datetime',
            'admin_password' => 'hashed',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(self::class, 'referred_by_id');
    }

    public function bonusTransactions(): HasMany
    {
        return $this->hasMany(BonusTransaction::class);
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Joriy faol (eng yuqori darajali, keyin eng uzoq muddatli) obuna; bo'lmasa null */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()->active()->get()
            ->sortByDesc(fn (Subscription $s) => [Subscription::PLAN_LEVELS[$s->plan] ?? 0, $s->expires_at->getTimestamp()])
            ->first();
    }

    /** Joriy tarif: free | standard | pro */
    public function currentPlan(): string
    {
        return $this->activeSubscription()?->plan ?? Subscription::PLAN_FREE;
    }

    /** Joriy tarif berilgan darajadan past emasmi (Pro ⊇ Standart ⊇ Free) */
    public function hasPlan(string $plan): bool
    {
        return (Subscription::PLAN_LEVELS[$this->currentPlan()] ?? 0) >= (Subscription::PLAN_LEVELS[$plan] ?? PHP_INT_MAX);
    }

    /** Savdo va ombor bo'limlari ochiqmi (Standart yoki Pro) */
    public function hasSalesAndInventory(): bool
    {
        return $this->hasPlan(Subscription::PLAN_STANDARD);
    }

    /** TZ 31/35: Pro tarif faolmi */
    public function isPro(): bool
    {
        return $this->hasPlan(Subscription::PLAN_PRO);
    }

    public function hasPin(): bool
    {
        return filled($this->pin);
    }

    /**
     * Profil to'liq deb hisoblanadi, agar ism kiritilgan bo'lsa.
     * `shop_name` ixtiyoriy (UpdateProfileRequest bilan mos) — TZ 4.
     */
    public function isProfileComplete(): bool
    {
        return filled($this->name);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path
            ? \Illuminate\Support\Facades\Storage::disk(config('savdodaftar.avatar.disk'))->url($this->avatar_path)
            : null;
    }
}
