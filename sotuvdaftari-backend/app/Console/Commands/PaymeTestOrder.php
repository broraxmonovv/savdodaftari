<?php

namespace App\Console\Commands;

use App\Exceptions\ApiException;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\BillingService;
use Illuminate\Console\Command;

/**
 * Payme sandbox (test.paycom.uz) uchun toza buyurtma yaratadi. Sandbox har sinovda yangi `order_id` talab qiladi:
 * oldingi sinovdan tranzaksiyasi qolgan buyurtmada CreateTransaction -31099 qaytaradi.
 */
class PaymeTestOrder extends Command
{
    protected $signature = 'payme:test-order {phone? : Foydalanuvchi telefoni (bo\'sh bo\'lsa birinchi foydalanuvchi)} {--plan=standard : standard yoki pro}';

    protected $description = 'Payme sandbox sinovi uchun yangi pending buyurtma (order_id va summa tiyinda)';

    public function handle(BillingService $billing): int
    {
        $plan = (string) $this->option('plan');

        if (! in_array($plan, Subscription::PAID_PLANS, true)) {
            $this->error('Tarif standard yoki pro bo\'lishi kerak.');

            return self::INVALID;
        }

        $phone = $this->argument('phone');
        $user = $phone ? User::where('phone', '+'.ltrim(preg_replace('/\D/', '', $phone), '+'))->first() : User::orderBy('id')->first();

        if ($user === null) {
            $this->error('Foydalanuvchi topilmadi.');

            return self::FAILURE;
        }

        // Oldingi tugallanmagan test buyurtmalarini yopib, har safar toza buyurtma beramiz
        Payment::forUser($user)->where('provider', Payment::PROVIDER_PAYME)->where('status', Payment::STATUS_PENDING)
            ->whereNull('transaction_id')->update(['status' => Payment::STATUS_CANCELED, 'canceled_at' => now()]);

        try {
            $payment = $billing->checkout($user, $plan, Payment::PROVIDER_PAYME);
        } catch (ApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Yangi Payme test buyurtmasi:');
        $this->table(['order_id', 'Summa (so\'m)', 'Summa (tiyin)', 'Foydalanuvchi'], [[
            $payment->order_id, number_format((float) $payment->amount, 0, '.', ' '), $payment->amountInTiyin(), $user->phone,
        ]]);
        $this->line('Sandbox: https://test.paycom.uz — "account" maydoniga shu order_id, summaga tiyindagi qiymatni kiriting.');
        $this->line('Checkout URL: '.($billing->checkoutUrl($payment) ?? '(PAYME_MERCHANT_ID sozlanmagan)'));

        return self::SUCCESS;
    }
}
