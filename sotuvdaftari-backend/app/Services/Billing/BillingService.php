<?php

namespace App\Services\Billing;

use App\Exceptions\ApiException;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TZ 31, 35, 36: Standart/Pro tarif checkout va to'lovni tasdiqlash.
 *
 * Asosiy tasdiqlash manbai — provayder webhook'i (TZ 36.1 5-band).
 * Barcha faollashtirish amallari idempotent (TZ 36.2).
 */
class BillingService
{
    public function price(string $plan): float
    {
        return (float) config("savdodaftar.billing.plans.{$plan}.price");
    }

    public function days(string $plan): int
    {
        return (int) config("savdodaftar.billing.plans.{$plan}.days");
    }

    /** Tariflar ro'yxati (mobil ilova tarif tanlash ekrani uchun) */
    public function plans(): array
    {
        return [
            [
                'id' => Subscription::PLAN_STANDARD,
                'price' => $this->price(Subscription::PLAN_STANDARD),
                'days' => $this->days(Subscription::PLAN_STANDARD),
                'features' => ['sales', 'inventory'],
            ],
            [
                'id' => Subscription::PLAN_PRO,
                'price' => $this->price(Subscription::PLAN_PRO),
                'days' => $this->days(Subscription::PLAN_PRO),
                'features' => ['sales', 'inventory', 'voice', 'ai_assistant', 'ocr_import', 'advanced_reports'],
            ],
        ];
    }

    /**
     * Yangi pending to'lov yaratadi yoki mavjudini qayta ishlatadi (ikki marta to'lov oldini olish).
     * Joriy tarifga teng yoki past tarifni sotib olib bo'lmaydi; Standartdan Pro'ga o'tish mumkin.
     */
    public function checkout(User $user, string $plan, string $provider): Payment
    {
        if ($user->hasPlan($plan)) {
            throw new ApiException(
                __($plan === Subscription::PLAN_PRO ? 'messages.billing.already_pro' : 'messages.billing.already_standard'),
                422,
                $plan === Subscription::PLAN_PRO ? 'already_pro' : 'already_standard',
            );
        }

        $amount = $this->price($plan);

        $pending = Payment::forUser($user)
            ->where('plan', $plan)
            ->where('provider', $provider)
            ->where('status', Payment::STATUS_PENDING)
            ->whereNull('transaction_id')
            ->latest('id')
            ->first();

        if ($pending !== null && (float) $pending->amount === $amount) {
            return $pending;
        }

        return Payment::create([
            'user_id' => $user->id,
            'plan' => $plan,
            'provider' => $provider,
            'amount' => $amount,
            'order_id' => $this->newOrderId(),
            'status' => Payment::STATUS_PENDING,
        ]);
    }

    /** Provayder checkout sahifasi URL manzili (sozlanmagan bo'lsa null) */
    public function checkoutUrl(Payment $payment): ?string
    {
        if ($payment->provider === Payment::PROVIDER_PAYME) {
            $merchantId = (string) config('savdodaftar.billing.payme.merchant_id');

            if ($merchantId === '') {
                return null;
            }

            $params = "m={$merchantId};ac.order_id={$payment->order_id};a={$payment->amountInTiyin()}";

            return rtrim((string) config('savdodaftar.billing.payme.checkout_url'), '/').'/'.base64_encode($params);
        }

        $serviceId = (string) config('savdodaftar.billing.click.service_id');
        $merchantId = (string) config('savdodaftar.billing.click.merchant_id');

        if ($serviceId === '' || $merchantId === '') {
            return null;
        }

        return config('savdodaftar.billing.click.checkout_url').'?'.http_build_query([
            'service_id' => $serviceId,
            'merchant_id' => $merchantId,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'transaction_param' => $payment->order_id,
        ]);
    }

    /**
     * To'lovni tasdiqlab Pro obunani yaratadi yoki uzaytiradi.
     * Idempotent: bir xil webhook ikki marta kelsa qayta faollashtirmaydi (TZ 36.2).
     */
    public function activate(Payment $payment, array $meta = []): Payment
    {
        return DB::transaction(function () use ($payment, $meta) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->isPaid()) {
                return $payment;
            }

            $subscription = $this->extendOrCreate($payment->user()->firstOrFail(), $payment->plan);

            $payment->update([
                'status' => Payment::STATUS_PAID,
                'paid_at' => now(),
                'subscription_id' => $subscription->id,
                'provider_state' => $payment->provider === Payment::PROVIDER_PAYME ? 2 : $payment->provider_state,
                'meta' => array_merge($payment->meta ?? [], $meta),
            ]);

            return $payment;
        });
    }

    /**
     * Payme CancelTransaction: state 1 → -1 (bekor), state 2 → -2 (storno — obuna qaytariladi).
     * Idempotent: allaqachon bekor qilingan bo'lsa o'zgartirmaydi.
     */
    public function cancelPayme(Payment $payment, int $reason, int $cancelTime): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $cancelTime) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (in_array((int) $payment->provider_state, [-1, -2], true)) {
                return $payment;
            }

            $newState = (int) $payment->provider_state === 2 ? -2 : -1;

            if ($newState === -2 && $payment->subscription_id !== null) {
                $subscription = Subscription::find($payment->subscription_id);

                if ($subscription !== null && $subscription->isActive()) {
                    $expiresAt = $subscription->expires_at->copy()->subDays($this->days($payment->plan));

                    $subscription->update($expiresAt->isPast()
                        ? ['status' => Subscription::STATUS_CANCELED, 'expires_at' => now()]
                        : ['expires_at' => $expiresAt]);
                }
            }

            $payment->update([
                'status' => Payment::STATUS_CANCELED,
                'canceled_at' => now(),
                'provider_state' => $newState,
                'meta' => array_merge($payment->meta ?? [], ['cancel_time' => $cancelTime, 'cancel_reason' => $reason]),
            ]);

            return $payment;
        });
    }

    /** Provayder xatosida pending to'lovni failed qiladi */
    public function fail(Payment $payment, ?string $note = null): Payment
    {
        if ($payment->isPending()) {
            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'meta' => array_merge($payment->meta ?? [], array_filter(['fail_note' => $note])),
            ]);
        }

        return $payment;
    }

    /** Shu tarifning faol obunasini uzaytiradi, bo'lmasa yangisini yaratadi */
    private function extendOrCreate(User $user, string $plan): Subscription
    {
        $active = $user->subscriptions()->active()->where('plan', $plan)->orderByDesc('expires_at')->first();

        if ($active !== null) {
            $active->update(['expires_at' => $active->expires_at->copy()->addDays($this->days($plan))]);

            return $active;
        }

        return Subscription::create([
            'user_id' => $user->id,
            'plan' => $plan,
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => now(),
            'expires_at' => now()->addDays($this->days($plan)),
        ]);
    }

    private function newOrderId(): string
    {
        do {
            $orderId = 'SD'.now()->format('ymd').Str::upper(Str::random(8));
        } while (Payment::query()->where('order_id', $orderId)->exists());

        return $orderId;
    }
}
