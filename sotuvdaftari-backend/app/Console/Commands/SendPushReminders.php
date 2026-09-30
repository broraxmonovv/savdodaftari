<?php

namespace App\Console\Commands;

use App\Models\Debt;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Push\PushService;
use Illuminate\Console\Command;

/** TZ 22: tarif tugashi, muddati o'tgan qarzlar va kam qoldiq bo'yicha kunlik push eslatmalar */
class SendPushReminders extends Command
{
    protected $signature = 'push:reminders';

    protected $description = 'Kunlik push eslatmalar (tarif muddati, qarzlar, kam qoldiq)';

    public function handle(PushService $push): int
    {
        $sent = 0;

        User::query()->whereHas('deviceTokens')->whereNull('blocked_at')->chunkById(200, function ($users) use ($push, &$sent) {
            foreach ($users as $user) {
                app()->setLocale($user->locale ?: 'uz');

                // Tarif 2-3 kundan keyin tugaydi (kuniga bir marta: 2..3 kun oralig'i)
                $expiring = $user->subscriptions()->active()
                    ->whereBetween('expires_at', [now()->addDays(2), now()->addDays(3)])->first();

                if ($expiring !== null) {
                    $sent += $push->toUser($user, __('messages.push.subscription_title'), __('messages.push.subscription_body', [
                        'plan' => ucfirst($expiring->plan),
                        'date' => $expiring->expires_at->format('d.m.Y'),
                    ]), ['type' => 'subscription_expiring']) > 0 ? 1 : 0;
                }

                if ($user->hasPlan(Subscription::PLAN_STANDARD)) {
                    $low = Product::forUser($user)->active()->needsAttention()->count();

                    if ($low > 0) {
                        $sent += $push->toUser($user, __('messages.push.low_stock_title'),
                            __('messages.push.low_stock_body', ['count' => $low]), ['type' => 'low_stock']) > 0 ? 1 : 0;
                    }
                }

                $overdue = Debt::forUser($user)->overdue()->count();

                if ($overdue > 0) {
                    $sent += $push->toUser($user, __('messages.push.debts_title'),
                        __('messages.push.debts_body', ['count' => $overdue]), ['type' => 'debt_overdue']) > 0 ? 1 : 0;
                }
            }
        });

        $this->info("Yuborildi: {$sent} ta foydalanuvchiga.");

        return self::SUCCESS;
    }
}
