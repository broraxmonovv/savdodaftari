<?php

namespace App\Services\Referral;

use App\Exceptions\ApiException;
use App\Models\BonusTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Referal dasturi: taklif qilingan foydalanuvchining har bir to'lovidan
 * taklif qilganga foiz (standart 10%) bonus balansiga yoziladi.
 */
class ReferralService
{
    public function percent(): float
    {
        return (float) config('savdodaftar.referral.percent');
    }

    public function link(User $user): string
    {
        return str_replace('{code}', $this->codeFor($user), (string) config('savdodaftar.referral.link'));
    }

    /** Foydalanuvchining referal kodi (yo'q bo'lsa yaratiladi) */
    public function codeFor(User $user): string
    {
        if (blank($user->referral_code)) {
            do {
                $code = Str::upper(Str::random(8));
            } while (User::withTrashed()->where('referral_code', $code)->exists());

            $user->forceFill(['referral_code' => $code])->save();
        }

        return $user->referral_code;
    }

    /** Yangi foydalanuvchini kod egasiga biriktiradi (faqat bir marta, o'zini o'zi taklif qila olmaydi) */
    public function attach(User $user, string $code): void
    {
        $code = Str::upper(trim($code));

        if ($user->referred_by_id !== null) {
            throw new ApiException(__('messages.referral.already_attached'), 422, 'referral_already_attached');
        }

        $referrer = User::where('referral_code', $code)->first();

        if ($referrer === null) {
            throw new ApiException(__('messages.referral.invalid'), 422, 'referral_invalid');
        }

        if ($referrer->id === $user->id) {
            throw new ApiException(__('messages.referral.self'), 422, 'referral_self');
        }

        $user->forceFill(['referred_by_id' => $referrer->id])->save();
    }

    /** To'langan to'lov uchun taklif qilganga bonus beradi. Idempotent (payment bo'yicha bir marta). */
    public function creditForPayment(Payment $payment): ?BonusTransaction
    {
        $payer = $payment->user;

        if ($payer === null || $payer->referred_by_id === null) {
            return null;
        }

        return DB::transaction(function () use ($payment, $payer) {
            $exists = BonusTransaction::where('payment_id', $payment->id)
                ->where('type', BonusTransaction::TYPE_REFERRAL)->exists();

            if ($exists) {
                return null;
            }

            $amount = round((float) $payment->amount * $this->percent() / 100, 2);

            if ($amount <= 0) {
                return null;
            }

            $transaction = BonusTransaction::create([
                'user_id' => $payer->referred_by_id,
                'from_user_id' => $payer->id,
                'payment_id' => $payment->id,
                'type' => BonusTransaction::TYPE_REFERRAL,
                'amount' => $amount,
            ]);

            User::whereKey($payer->referred_by_id)->increment('bonus_balance', $amount);

            return $transaction;
        });
    }

    /** To'lov storno qilinsa (Payme) berilgan bonus qaytarib olinadi. Idempotent. */
    public function reverseForPayment(Payment $payment): ?BonusTransaction
    {
        return DB::transaction(function () use ($payment) {
            $credit = BonusTransaction::where('payment_id', $payment->id)
                ->where('type', BonusTransaction::TYPE_REFERRAL)->first();

            if ($credit === null || BonusTransaction::where('payment_id', $payment->id)
                ->where('type', BonusTransaction::TYPE_REVERSAL)->exists()) {
                return null;
            }

            $reversal = BonusTransaction::create([
                'user_id' => $credit->user_id,
                'from_user_id' => $credit->from_user_id,
                'payment_id' => $payment->id,
                'type' => BonusTransaction::TYPE_REVERSAL,
                'amount' => -1 * (float) $credit->amount,
            ]);

            User::whereKey($credit->user_id)->decrement('bonus_balance', (float) $credit->amount);

            return $reversal;
        });
    }

    /** Referal statistikasi */
    public function stats(User $user): array
    {
        $referrals = User::where('referred_by_id', $user->id);

        return [
            'invited_count' => (clone $referrals)->count(),
            'paying_count' => (clone $referrals)->whereHas('payments', fn ($q) => $q->where('status', Payment::STATUS_PAID))->count(),
            'earned_total' => (float) BonusTransaction::forUser($user)->where('type', BonusTransaction::TYPE_REFERRAL)->sum('amount'),
        ];
    }
}
