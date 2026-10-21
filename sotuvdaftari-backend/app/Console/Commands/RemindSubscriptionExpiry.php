<?php

namespace App\Console\Commands;

use App\Exceptions\SmsException;
use App\Jobs\SendAnnouncementPush;
use App\Models\Announcement;
use App\Models\Subscription;
use App\Services\Sms\SmsSender;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Tarif (bepul sinov ham, pullik Standart/Pro ham) tugashiga 1 kun qolganda foydalanuvchiga eslatma:
 *  - SMS (doim o'zbek tilida): "Sizning Savdo Up {tarif} tarifingiz tugashiga 1 kun qoldi ...";
 *  - ilova ichidagi bildirishnoma (e'lon) va push (foydalanuvchi tilida).
 * Har obuna uchun bir marta. Tugash sanasi mahalliy (Toshkent) vaqt bo'yicha ertaga bo'lgan obunalar tanlanadi.
 */
class RemindSubscriptionExpiry extends Command
{
    protected $signature = 'subscriptions:remind-expiring {--dry-run : Yubormasdan faqat ro\'yxatni ko\'rsatadi}';

    protected $description = 'Tarif tugashiga 1 kun qolganda SMS va bildirishnoma yuboradi';

    public function handle(SmsSender $sms): int
    {
        $tz = (string) config('savdodaftar.timezone', 'Asia/Tashkent');
        $tomorrow = Carbon::now($tz)->addDay();
        $from = $tomorrow->copy()->startOfDay()->utc();
        $to = $tomorrow->copy()->endOfDay()->utc();
        $sent = 0;

        Subscription::query()->with('user')
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereBetween('expires_at', [$from, $to])
            ->whereNull('expiry_reminded_at')
            ->each(function (Subscription $sub) use ($sms, &$sent) {
                $user = $sub->user;

                if ($user === null || $user->blocked_at !== null) {
                    return;
                }

                // Foydalanuvchi allaqachon uzoqroq amal qiladigan boshqa obunaga ega bo'lsa (masalan, sinovda to'lov qilgan)
                $hasLonger = Subscription::where('user_id', $user->id)->active()
                    ->where('id', '!=', $sub->id)->where('expires_at', '>', $sub->expires_at)->exists();

                if ($hasLonger) {
                    $sub->update(['expiry_reminded_at' => now()]);

                    return;
                }

                $planUz = $this->planName($sub, 'uz');

                if ($this->option('dry-run')) {
                    $this->line("{$user->phone}: ".__('messages.expiry.sms', ['plan' => $planUz], 'uz'));
                    $sent++;

                    return;
                }

                // Ilova ichidagi bildirishnoma va push (foydalanuvchi tilida)
                $locale = $user->locale ?: 'uz';
                $announcement = Announcement::create([
                    'title' => __('messages.expiry.title', [], $locale),
                    'body' => __('messages.expiry.body', ['plan' => $this->planName($sub, $locale)], $locale),
                    'audience' => Announcement::AUDIENCE_USER,
                    'user_id' => $user->id,
                ]);
                SendAnnouncementPush::dispatch($announcement->id);

                // SMS — doim o'zbek tilida
                try {
                    $sms->send($user->phone, __('messages.expiry.sms', ['plan' => $planUz], 'uz'));
                } catch (SmsException $e) {
                    Log::warning('Tarif tugashi SMS yuborilmadi', ['user' => $user->id, 'error' => $e->getMessage()]);
                }

                $sub->update(['expiry_reminded_at' => now()]);
                $sent++;
            });

        $this->info("Tarif tugashi eslatmasi: {$sent} ta.");

        return self::SUCCESS;
    }

    private function planName(Subscription $sub, string $locale): string
    {
        $name = $sub->plan === Subscription::PLAN_PRO ? 'Pro' : ($locale === 'ru' ? 'Стандарт' : 'Standart');

        return $sub->is_trial ? __('messages.expiry.free_prefix', [], $locale).' '.$name : $name;
    }
}
