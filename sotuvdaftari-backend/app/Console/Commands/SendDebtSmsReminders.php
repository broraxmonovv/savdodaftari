<?php

namespace App\Console\Commands;

use App\Exceptions\SmsException;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\User;
use App\Services\Sms\SmsSender;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Qarzdor mijozlarga SMS eslatma:
 *  - qaytarish muddatiga 1 kun qolganda (bir marta);
 *  - muddati o'tgach (birinchi kuni, so'ng har `overdue_repeat_days` kunda).
 * Bir mijozning bir nechta qarzi bitta SMS'da jamlanadi.
 */
class SendDebtSmsReminders extends Command
{
    protected $signature = 'debts:sms-reminders {--dry-run : SMS yubormasdan faqat sanab chiqadi}';

    protected $description = 'Qarz muddati tugayotgan va o\'tib ketgan mijozlarga SMS eslatma yuborish';

    public function handle(SmsSender $sms): int
    {
        $sent = 0;

        User::query()->whereNull('blocked_at')->where('sms_reminders', true)
            ->whereHas('debts', fn ($q) => $q->unpaid()->whereNotNull('due_date'))
            ->chunkById(100, function ($owners) use ($sms, &$sent) {
                foreach ($owners as $owner) {
                    $sent += $this->remind($owner, $sms, 'debt_due_soon');
                    $sent += $this->remind($owner, $sms, 'debt_overdue');
                }
            });

        $this->info("SMS yuborildi: {$sent} ta.");

        return self::SUCCESS;
    }

    private function remind(User $owner, SmsSender $sms, string $kind): int
    {
        $repeat = max(1, (int) config('savdodaftar.sms_reminders.overdue_repeat_days'));

        $debts = Debt::forUser($owner)->unpaid()->with('customer')->whereHas('customer', fn ($q) => $q->whereNotNull('phone'))
            ->when($kind === 'debt_due_soon',
                fn ($q) => $q->whereDate('due_date', today()->addDay())->whereNull('due_soon_sms_at'),
                fn ($q) => $q->whereDate('due_date', '<', today())
                    ->where(fn ($w) => $w->whereNull('overdue_sms_at')->orWhereDate('overdue_sms_at', '<=', today()->subDays($repeat))))
            ->get();

        $count = 0;

        /** @var Collection<int, Debt> $group */
        foreach ($debts->groupBy('customer_id') as $group) {
            /** @var Customer $customer */
            $customer = $group->first()->customer;
            $amount = $group->sum(fn (Debt $debt) => $debt->remaining);

            if ($amount <= 0) {
                continue;
            }

            $message = __("messages.sms.{$kind}", [
                'name' => $customer->name,
                'shop' => $owner->shop_name ?: $owner->name,
                'owner' => $owner->name,
                'amount' => number_format($amount, 0, '.', ' '),
            ], $owner->locale ?: 'uz');

            if ($this->option('dry-run')) {
                $this->line("{$customer->phone}: {$message}");
                $count++;

                continue;
            }

            try {
                $sms->send($customer->phone, $message);
            } catch (SmsException $e) {
                Log::warning('Qarz SMS eslatmasi yuborilmadi', ['customer' => $customer->id, 'error' => $e->getMessage()]);

                continue;
            }

            Debt::whereIn('id', $group->pluck('id'))->update([$kind === 'debt_due_soon' ? 'due_soon_sms_at' : 'overdue_sms_at' => today()]);
            $count++;
        }

        return $count;
    }
}
