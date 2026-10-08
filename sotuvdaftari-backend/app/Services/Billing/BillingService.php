<?php

namespace App\Services\Billing;

use App\Exceptions\ApiException;
use App\Models\Announcement;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Referral\ReferralService;
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
    public function __construct(private readonly ReferralService $referrals) {}

    /** Tarif qatori (admin panelda tahrirlanadi); bazada bo'lmasa config'dagi qiymatlar ishlatiladi */
    private function row(string $plan): ?Plan
    {
        return Plan::where('key', $plan)->first();
    }

    public function price(string $plan): float
    {
        return (float) ($this->row($plan)?->price ?? config("savdodaftar.billing.plans.{$plan}.price"));
    }

    public function days(string $plan): int
    {
        return Plan::DAYS;
    }

    /** Faol tariflar ro'yxati (mobil ilova tarif tanlash ekrani uchun) */
    public function plans(): array
    {
        Plan::ensureDefaults();

        return Plan::where('is_active', true)->orderBy('sort')->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->key,
                'price' => $plan->price,
                'days' => Plan::DAYS,
                'limits' => ['customers' => $plan->max_customers, 'products' => $plan->max_products],
                'features' => $plan->features ?? [],
            ])->values()->all();
    }

    /**
     * Yangi pending to'lov yaratadi yoki mavjudini qayta ishlatadi (ikki marta to'lov oldini olish).
     * Joriy tarifga teng yoki past tarifni sotib olib bo'lmaydi; Standartdan Pro'ga o'tish mumkin.
     */
    public function checkout(User $user, string $plan, string $provider): Payment
    {
        $this->assertCanBuy($user, $plan);

        $amount = $this->price($plan);

        $pending = Payment::forUser($user)
            ->where('plan', $plan)
            ->where('provider', $provider)
            ->where('status', Payment::STATUS_PENDING)
            ->whereNull('transaction_id')
            // Faqat yaqinda yaratilgan buyurtma qayta ishlatiladi; eskirgani uchun yangi buyurtma beriladi
            ->where('created_at', '>=', now()->subMinutes((int) config('savdodaftar.billing.checkout_reuse_minutes', 30)))
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

    /** Foydalanuvchi hozir bepul sinov Standartida (pullik tarifsiz) */
    public function isOnTrial(User $user): bool
    {
        $active = $user->activeSubscription();

        return $active !== null && $active->is_trial && $active->plan === Subscription::PLAN_STANDARD;
    }

    /**
     * Yangi foydalanuvchiga bepul Standart sinov beradi (`TRIAL_DAYS`, standart 14 kun). Faqat bir marta:
     * `trial_started_at` belgilanadi, shuning uchun o'chirib qayta ro'yxatdan o'tish ham qayta bermaydi.
     */
    public function grantTrial(User $user): ?Subscription
    {
        $days = (int) config('savdodaftar.trial.days');

        if ($days <= 0 || $user->trial_started_at !== null) {
            return null;
        }

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => Subscription::PLAN_STANDARD,
            'is_trial' => true,
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => now(),
            'expires_at' => now()->addDays($days),
        ]);

        $user->forceFill(['trial_started_at' => now()])->save();

        $locale = $user->locale ?: 'uz';

        Announcement::create([
            'title' => __('messages.trial.welcome_title', [], $locale),
            'body' => __('messages.trial.welcome_body', [
                'days' => $days,
                'date' => $subscription->expires_at->format('d.m.Y'),
            ], $locale),
            'audience' => Announcement::AUDIENCE_USER,
            'user_id' => $user->id,
        ]);

        return $subscription;
    }

    /** Joriy tarifga teng yoki past tarifni sotib olib bo'lmaydi */
    public function assertCanBuy(User $user, string $plan): void
    {
        // Bepul sinovdagi Standartni pullik qilib 30 kunga cho'zish mumkin
        if ($plan === Subscription::PLAN_STANDARD && $this->isOnTrial($user)) {
            return;
        }

        Plan::ensureDefaults();

        if (! Plan::where('key', $plan)->where('is_active', true)->exists()) {
            throw new ApiException(__('messages.billing.plan_unavailable'), 422, 'plan_unavailable');
        }

        if ($user->hasPlan($plan)) {
            throw new ApiException(
                __($plan === Subscription::PLAN_PRO ? 'messages.billing.already_pro' : 'messages.billing.already_standard'),
                422,
                $plan === Subscription::PLAN_PRO ? 'already_pro' : 'already_standard',
            );
        }
    }

    /** Bonus balansi hisobidan to'lov yozuvi (status: pending) — [BonusService] tasdiqlaydi */
    public function createBonusPayment(User $user, string $plan): Payment
    {
        return Payment::create([
            'user_id' => $user->id,
            'plan' => $plan,
            'provider' => Payment::PROVIDER_BONUS,
            'amount' => $this->price($plan),
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

            $field = (string) config('savdodaftar.billing.payme.account_field', 'order_id');
            $params = "m={$merchantId};ac.{$field}={$payment->order_id};a={$payment->amountInTiyin()}";

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

            // Taklif qilganga ulush (idempotent). Bonus hisobidan to'langan to'lov ulush bermaydi.
            if ($payment->provider !== Payment::PROVIDER_BONUS) {
                $this->referrals->creditForPayment($payment);
            }

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

            if ($newState === -2) {
                $this->referrals->reverseForPayment($payment);
            }

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
            // Sinov muddati tugashidan boshlab 30 kun qo'shiladi; obuna endi pullik
            $active->update([
                'expires_at' => $active->expires_at->copy()->addDays($this->days($plan)),
                'is_trial' => false,
            ]);

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
