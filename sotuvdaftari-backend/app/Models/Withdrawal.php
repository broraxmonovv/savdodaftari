<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    use BelongsToUser;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id', 'amount', 'card_number', 'card_holder', 'status',
        'admin_note', 'processed_by', 'processed_at',
    ];

    protected $hidden = ['card_number'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'card_number' => 'encrypted',
            'processed_at' => 'datetime',
        ];
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** `8600 **** **** 1234` */
    public function maskedCard(): string
    {
        $digits = (string) $this->card_number;

        return substr($digits, 0, 4).' **** **** '.substr($digits, -4);
    }
}
