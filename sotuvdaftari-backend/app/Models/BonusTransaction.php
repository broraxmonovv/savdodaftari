<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonusTransaction extends Model
{
    use BelongsToUser;

    public const TYPE_REFERRAL = 'referral';

    public const TYPE_REVERSAL = 'reversal';

    /** Tarif to'lovi bonus balansidan (manfiy) */
    public const TYPE_PLAN_PAYMENT = 'plan_payment';

    /** Pul yechib olish so'rovi (manfiy) */
    public const TYPE_WITHDRAWAL = 'withdrawal';

    /** Yechib olish rad etildi — summa qaytdi (musbat) */
    public const TYPE_WITHDRAWAL_REFUND = 'withdrawal_refund';

    protected $fillable = ['user_id', 'from_user_id', 'payment_id', 'withdrawal_id', 'type', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
