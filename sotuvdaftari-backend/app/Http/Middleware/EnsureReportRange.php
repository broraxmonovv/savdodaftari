<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\Subscription;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TZ 35: Bepul/Standart tarifda hisobot faqat Bugun va 7 kun. 30 kun, "barchasi" va
 * ixtiyoriy sana oralig'i (7 kundan uzun) — Pro.
 */
class EnsureReportRange
{
    public const FREE_MAX_DAYS = 7;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->hasPlan(Subscription::PLAN_PRO)) {
            return $next($request);
        }

        $period = $request->query('period');
        $from = $request->query('from');
        $to = $request->query('to');

        $extended = in_array($period, ['month', 'all'], true);

        if (! $extended && ($from || $to)) {
            try {
                $extended = ! ($from && $to)
                    || Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1 > self::FREE_MAX_DAYS;
            } catch (\Throwable) {
                $extended = false; // noto'g'ri sana — validatsiya xatosini controller qaytaradi
            }
        }

        if ($extended) {
            throw new ApiException(
                __('messages.billing.plan_required', ['plan' => 'Pro']),
                403,
                'plan_required',
                ['required_plan' => Subscription::PLAN_PRO, 'feature' => 'advanced_reports'],
            );
        }

        return $next($request);
    }
}
