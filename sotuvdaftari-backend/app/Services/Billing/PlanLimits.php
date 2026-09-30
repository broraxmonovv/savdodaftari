<?php

namespace App\Services\Billing;

use App\Exceptions\ApiException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

/**
 * TZ 35: Pro — cheksiz mijoz/mahsulot. Bepul va Standart tarifda limit bor:
 * Bepul — `FREE_MAX_CUSTOMERS` (config), Standart — `plans.max_customers/max_products`
 * (admin panelda o'zgartiriladi, NULL = cheksiz). Pro uchun limit yo'q.
 */
class PlanLimits
{
    public const CUSTOMERS = 'customers';

    public const PRODUCTS = 'products';

    /** @return ?int null — cheksiz */
    public function limit(User $user, string $resource): ?int
    {
        $plan = $user->currentPlan();

        if ($plan === Subscription::PLAN_FREE) {
            return $resource === self::CUSTOMERS
                ? (int) config('savdodaftar.limits.free_customers')
                : 0; // Bepul tarifda ombor yopiq (mahsulot qo'shib bo'lmaydi)
        }

        $value = Plan::where('key', $plan)->value($resource === self::CUSTOMERS ? 'max_customers' : 'max_products');

        return $value === null ? null : (int) $value;
    }

    /** Limitga yetgan bo'lsa ApiException (403 limit_reached) tashlaydi */
    public function assertCanAdd(User $user, string $resource, int $currentCount): void
    {
        $limit = $this->limit($user, $resource);

        if ($limit === null || $currentCount < $limit) {
            return;
        }

        $next = $user->currentPlan() === Subscription::PLAN_FREE ? Subscription::PLAN_STANDARD : Subscription::PLAN_PRO;

        throw new ApiException(
            __('messages.limits.'.$resource, ['limit' => $limit, 'plan' => ucfirst($next)]),
            403,
            'limit_reached',
            ['resource' => $resource, 'limit' => $limit, 'required_plan' => $next],
        );
    }
}
