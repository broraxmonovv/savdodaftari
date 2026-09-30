<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** TZ 31, 35, 36: tarif holati va Standart/Pro checkout */
class BillingController extends Controller
{
    use RespondsWithJson;

    public function __construct(private readonly BillingService $billing) {}

    /** GET /billing/plan — joriy tarif va taklif qilinadigan tariflar (TZ 35) */
    public function plan(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->activeSubscription();

        return $this->success([
            'plan' => $user->currentPlan(),
            'expires_at' => $subscription?->expires_at?->toIso8601String(),
            // Bepul sinov: active — hozir sinovda, used — sinov berilgan (qayta berilmaydi)
            'is_trial' => (bool) $subscription?->is_trial,
            'trial' => [
                'days' => (int) config('savdodaftar.trial.days'),
                'active' => (bool) $subscription?->is_trial,
                'used' => $user->trial_started_at !== null,
                'expires_at' => $subscription?->is_trial ? $subscription->expires_at->toIso8601String() : null,
            ],
            'free' => ['limits' => ['customers' => (int) config('savdodaftar.limits.free_customers'), 'products' => 0]],
            'plans' => $this->billing->plans(),
            'providers' => Payment::PROVIDERS,
            // Eski mobil versiyalar bilan moslik
            'pro_price' => $this->billing->price(Subscription::PLAN_PRO),
            'pro_days' => $this->billing->days(Subscription::PLAN_PRO),
        ]);
    }

    /** POST /billing/checkout {plan: standard|pro, provider: payme|click} — pending to'lov va checkout URL (TZ 36.1) */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['nullable', Rule::in(Subscription::PAID_PLANS)],
            'provider' => ['required', Rule::in(Payment::PROVIDERS)],
        ]);

        $payment = $this->billing->checkout(
            $request->user(),
            $data['plan'] ?? Subscription::PLAN_PRO,
            $data['provider'],
        );

        return $this->success([
            'payment' => new PaymentResource($payment),
            'checkout_url' => $this->billing->checkoutUrl($payment),
        ], __('messages.billing.checkout_created'), 201);
    }

    /** GET /billing/payments/{orderId} — to'lov holatini polling qilish (TZ 36.1, 7-band) */
    public function payment(Request $request, string $orderId): JsonResponse
    {
        $payment = Payment::forUser($request->user())->where('order_id', $orderId)->firstOrFail();

        return $this->success([
            'payment' => new PaymentResource($payment),
            'plan' => $request->user()->currentPlan(),
        ]);
    }
}
