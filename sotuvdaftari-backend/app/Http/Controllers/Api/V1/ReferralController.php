<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Resources\BonusTransactionResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\WithdrawalResource;
use App\Models\BonusTransaction;
use App\Models\Subscription;
use App\Models\Withdrawal;
use App\Services\Referral\BonusService;
use Illuminate\Validation\Rule;
use App\Services\Referral\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Referal havola va bonus balansi */
class ReferralController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly ReferralService $referrals,
        private readonly BonusService $bonuses,
    ) {}

    /** GET /referral — kod, havola, foiz va statistika */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'code' => $this->referrals->codeFor($user),
            'link' => $this->referrals->link($user),
            'percent' => $this->referrals->percent(),
        ] + $this->referrals->stats($user));
    }

    /** GET /bonuses?per_page= — balans va bonus tarixi */
    public function bonuses(Request $request): JsonResponse
    {
        $data = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $user = $request->user();

        $query = BonusTransaction::forUser($user)->with(['fromUser', 'payment'])->latest('id');

        return $this->paginated(
            $query->paginate($data['per_page'] ?? 30),
            BonusTransactionResource::class,
            ['balance' => (float) $user->fresh()->bonus_balance],
        );
    }

    /** POST /bonuses/pay-plan {plan: standard|pro} — tarifni bonus balansidan to'lash */
    public function payPlan(Request $request): JsonResponse
    {
        $data = $request->validate(['plan' => ['required', Rule::in(Subscription::PAID_PLANS)]]);

        $payment = $this->bonuses->payForPlan($request->user(), $data['plan']);
        $user = $request->user()->fresh();

        return $this->success([
            'payment' => new PaymentResource($payment),
            'plan' => $user->currentPlan(),
            'balance' => (float) $user->bonus_balance,
        ], __('messages.bonus.plan_paid'), 201);
    }

    /** GET /withdrawals — foydalanuvchining yechib olish so'rovlari */
    public function withdrawals(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->paginated(
            Withdrawal::forUser($user)->latest('id')->paginate(30),
            WithdrawalResource::class,
            ['balance' => (float) $user->fresh()->bonus_balance, 'min_withdrawal' => $this->bonuses->minWithdrawal()],
        );
    }

    /** POST /withdrawals {amount, card_number, card_holder?} — adminga yuboriladi */
    public function requestWithdrawal(Request $request): JsonResponse
    {
        $request->merge(['card_number' => preg_replace('/\D+/', '', (string) $request->input('card_number'))]);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'card_number' => ['required', 'digits:16', function (string $attribute, mixed $value, \Closure $fail) {
                if (! $this->luhn((string) $value)) {
                    $fail(__('messages.bonus.card_invalid'));
                }
            }],
            'card_holder' => ['nullable', 'string', 'max:100'],
        ]);

        $withdrawal = $this->bonuses->requestWithdrawal(
            $request->user(),
            (float) $data['amount'],
            $data['card_number'],
            $data['card_holder'] ?? null,
        );

        return $this->success([
            'withdrawal' => new WithdrawalResource($withdrawal),
            'balance' => (float) $request->user()->fresh()->bonus_balance,
        ], __('messages.bonus.withdrawal_sent'), 201);
    }

    /** Plastik karta raqami nazorat yig'indisi (Luhn) */
    private function luhn(string $number): bool
    {
        $sum = 0;
        $alt = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $n = (int) $number[$i];

            if ($alt) {
                $n *= 2;
                $n = $n > 9 ? $n - 9 : $n;
            }

            $sum += $n;
            $alt = ! $alt;
        }

        return $sum % 10 === 0;
    }
}
