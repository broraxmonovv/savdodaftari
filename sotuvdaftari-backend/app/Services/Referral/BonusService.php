<?php

namespace App\Services\Referral;

use App\Exceptions\ApiException;
use App\Models\BonusTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Billing\BillingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bonus balansini sarflash: tarif to'lovi va kartaga yechib olish.
 * Barcha amallar foydalanuvchi qatori qulflangan holda, tranzaksiyada bajariladi.
 */
class BonusService
{
    public function __construct(private readonly BillingService $billing) {}

    public function minWithdrawal(): float
    {
        return (float) config('savdodaftar.referral.min_withdrawal');
    }

    /** Tarifni to'liq bonus balansidan to'laydi va darhol faollashtiradi */
    public function payForPlan(User $user, string $plan): Payment
    {
        return DB::transaction(function () use ($user, $plan) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            $this->billing->assertCanBuy($locked, $plan);

            $price = $this->billing->price($plan);

            if ((float) $locked->bonus_balance < $price) {
                throw new ApiException(__('messages.bonus.insufficient'), 422, 'insufficient_bonus', [
                    'balance' => (float) $locked->bonus_balance,
                    'required' => $price,
                ]);
            }

            $payment = $this->billing->createBonusPayment($locked, $plan);
            $payment = $this->billing->activate($payment);

            $locked->decrement('bonus_balance', $price);

            BonusTransaction::create([
                'user_id' => $locked->id,
                'payment_id' => $payment->id,
                'type' => BonusTransaction::TYPE_PLAN_PAYMENT,
                'amount' => -$price,
            ]);

            return $payment;
        });
    }

    /** Yechib olish so'rovi: summa balansdan darhol ushlab qolinadi, admin to'lab beradi */
    public function requestWithdrawal(User $user, float $amount, string $card, ?string $holder): Withdrawal
    {
        $withdrawal = DB::transaction(function () use ($user, $amount, $card, $holder) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($amount < $this->minWithdrawal()) {
                throw new ApiException(
                    __('messages.bonus.min_withdrawal', ['amount' => number_format($this->minWithdrawal(), 0, '.', ' ')]),
                    422,
                    'withdrawal_below_minimum',
                    ['min' => $this->minWithdrawal()],
                );
            }

            if ((float) $locked->bonus_balance < $amount) {
                throw new ApiException(__('messages.bonus.insufficient'), 422, 'insufficient_bonus', [
                    'balance' => (float) $locked->bonus_balance,
                    'required' => $amount,
                ]);
            }

            $withdrawal = Withdrawal::create([
                'user_id' => $locked->id,
                'amount' => $amount,
                'card_number' => $card,
                'card_holder' => $holder,
                'status' => Withdrawal::STATUS_PENDING,
            ]);

            $locked->decrement('bonus_balance', $amount);

            BonusTransaction::create([
                'user_id' => $locked->id,
                'withdrawal_id' => $withdrawal->id,
                'type' => BonusTransaction::TYPE_WITHDRAWAL,
                'amount' => -$amount,
            ]);

            return $withdrawal;
        });

        $this->notifyAdmin($withdrawal, $user);

        return $withdrawal;
    }

    /** Admin pulni kartaga o'tkazdi */
    public function markPaid(Withdrawal $withdrawal, User $admin, ?string $note = null): Withdrawal
    {
        return $this->process($withdrawal, $admin, Withdrawal::STATUS_PAID, $note);
    }

    /** Admin rad etdi — summa foydalanuvchi balansiga qaytariladi */
    public function reject(Withdrawal $withdrawal, User $admin, ?string $note = null): Withdrawal
    {
        return $this->process($withdrawal, $admin, Withdrawal::STATUS_REJECTED, $note);
    }

    private function process(Withdrawal $withdrawal, User $admin, string $status, ?string $note): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $admin, $status, $note) {
            $withdrawal = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->id);

            if (! $withdrawal->isPending()) {
                throw new ApiException(__('messages.bonus.already_processed'), 422, 'withdrawal_already_processed');
            }

            $withdrawal->update([
                'status' => $status,
                'admin_note' => $note,
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ]);

            if ($status === Withdrawal::STATUS_REJECTED) {
                User::whereKey($withdrawal->user_id)->increment('bonus_balance', (float) $withdrawal->amount);

                BonusTransaction::create([
                    'user_id' => $withdrawal->user_id,
                    'withdrawal_id' => $withdrawal->id,
                    'type' => BonusTransaction::TYPE_WITHDRAWAL_REFUND,
                    'amount' => (float) $withdrawal->amount,
                ]);
            }

            return $withdrawal;
        });
    }

    /** Adminga Telegram orqali xabar (sozlanmagan yoki xato bo'lsa so'rov baribir saqlanadi) */
    private function notifyAdmin(Withdrawal $withdrawal, User $user): void
    {
        $token = config('savdodaftar.admin.telegram_bot_token');
        $chat = config('savdodaftar.admin.telegram_chat_id');

        if (blank($token) || blank($chat)) {
            return;
        }

        try {
            $text = sprintf(
                "Bonusni yechib olish so'rovi #%d\nFoydalanuvchi: %s (%s)\nSumma: %s so'm\nKarta: %s%s",
                $withdrawal->id,
                $user->name ?: '-',
                $user->phone,
                number_format((float) $withdrawal->amount, 0, '.', ' '),
                $withdrawal->card_number,
                $withdrawal->card_holder ? "\nEgasi: {$withdrawal->card_holder}" : '',
            );

            Http::timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chat,
                'text' => $text,
            ]);
        } catch (Throwable $e) {
            Log::warning('Adminga Telegram xabari yuborilmadi: '.$e->getMessage());
        }
    }
}
