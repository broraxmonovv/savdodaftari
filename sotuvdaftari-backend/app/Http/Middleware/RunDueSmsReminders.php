<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Zaxira ishga tushirgich: serverda cron (`schedule:run`) sozlanmagan yoki to'xtab qolgan bo'lsa ham
 * qarz SMS eslatmalari yuborilsin. Belgilangan vaqtdan (09:00, Toshkent) keyingi birinchi API so'rovdan
 * so'ng, javob foydalanuvchiga qaytarilgach, `debts:sms-reminders` va `subscriptions:remind-expiring` ishga tushadi.
 *
 * Buyruq o'zi takroriy SMS yubormaydi (har bir qarz uchun yuborilgan sana belgilanadi), shuning uchun cron
 * bilan birga ishlashi xavfsiz. Kech soat (20:00) dan keyin ishga tushmaydi — SMS tunda ketmasin.
 */
class RunDueSmsReminders
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /** Javob yuborilgandan keyin bajariladi — foydalanuvchi kutmaydi */
    public function terminate(Request $request, Response $response): void
    {
        if (! config('savdodaftar.sms_reminders.web_fallback')) {
            return;
        }

        $now = Carbon::now((string) config('savdodaftar.timezone', 'Asia/Tashkent'));
        [$hour, $minute] = array_map('intval', explode(':', (string) config('savdodaftar.sms_reminders.send_at', '09:00')) + [0, 0]);
        $start = $now->copy()->setTime($hour, $minute);

        if ($now->lt($start) || $now->gte($now->copy()->setTime(20, 0))) {
            return;
        }

        // Har 20 daqiqada bir marta (xato bo'lsa keyingi oynada qayta uriniladi)
        $slot = intdiv((int) $start->diffInMinutes($now), 20);

        if (! Cache::add('debts-sms-fallback:'.$now->toDateString().':'.$slot, true, now()->addHours(2))) {
            return;
        }

        try {
            Artisan::call('debts:sms-reminders');
            Artisan::call('subscriptions:remind-expiring');
        } catch (Throwable $e) {
            report($e);
        }
    }
}
