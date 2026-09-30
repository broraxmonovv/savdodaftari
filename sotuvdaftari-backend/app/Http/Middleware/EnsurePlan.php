<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bo'limga kirishni tarif bo'yicha cheklaydi: `plan:standard` yoki `plan:pro`.
 * Yuqori tarif pastkisini o'z ichiga oladi (Pro ⊇ Standart).
 */
class EnsurePlan
{
    public function handle(Request $request, Closure $next, string $plan = Subscription::PLAN_STANDARD): Response
    {
        if (! $request->user()?->hasPlan($plan)) {
            throw new ApiException(
                __('messages.billing.plan_required', ['plan' => ucfirst($plan)]),
                403,
                'plan_required',
                ['required_plan' => $plan],
            );
        }

        return $next($request);
    }
}
