<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Sms\ArraySmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscriptionExpiryReminderTest extends TestCase
{
    use RefreshDatabase;

    private function sub(User $user, string $plan, bool $trial, Carbon $expires): Subscription
    {
        return Subscription::create([
            'user_id' => $user->id, 'plan' => $plan, 'is_trial' => $trial, 'status' => 'active',
            'started_at' => now()->subDays(10), 'expires_at' => $expires,
        ]);
    }

    public function test_trial_and_paid_plans_get_sms_and_notification_one_day_before(): void
    {
        ArraySmsSender::flush();
        $this->travelTo(Carbon::parse('2026-10-22 09:00', 'Asia/Tashkent'));
        $tomorrow = Carbon::parse('2026-10-23 14:00', 'Asia/Tashkent');

        $trialUser = User::factory()->create(['phone' => '+998901110001', 'locale' => 'uz']);
        $proUser = User::factory()->create(['phone' => '+998901110002', 'locale' => 'ru']);
        $later = User::factory()->create(['phone' => '+998901110003']);
        $blocked = User::factory()->create(['phone' => '+998901110004', 'blocked_at' => now()]);

        $this->sub($trialUser, 'standard', true, $tomorrow);
        $this->sub($proUser, 'pro', false, $tomorrow);
        $this->sub($later, 'standard', false, $tomorrow);          // lekin uzoqroq obunasi bor
        $this->sub($later, 'pro', false, $tomorrow->copy()->addDays(30));
        $this->sub($blocked, 'pro', false, $tomorrow);
        // Ertaga emas — tegilmaydi
        $this->sub(User::factory()->create(['phone' => '+998901110005']), 'pro', false, $tomorrow->copy()->addDays(5));

        $this->artisan('subscriptions:remind-expiring')->assertSuccessful();

        $sms = collect(ArraySmsSender::all())->keyBy('phone');
        $this->assertCount(2, $sms);

        // Bepul sinov: tarif nomi "bepul Standart", SMS o'zbekcha
        $this->assertSame(
            "Sizning Savdo Up bepul Standart tarifingiz tugashiga 1 kun qoldi. Yoqishni unutmang, aks holda ilovaning tarif imkoniyatlari o'chadi.",
            $sms['+998901110001']['message'],
        );
        // Pro foydalanuvchi rus tilida bo'lsa ham SMS o'zbekcha
        $this->assertStringContainsString('Savdo Up Pro tarifingiz tugashiga 1 kun qoldi', $sms['+998901110002']['message']);

        // Ilova ichidagi bildirishnoma: foydalanuvchi tilida
        $uz = Announcement::where('user_id', $trialUser->id)->latest('id')->first();
        $this->assertSame('Tarif tugashiga 1 kun qoldi', $uz->title);
        $this->assertStringContainsString('bepul Standart', $uz->body);
        $ru = Announcement::where('user_id', $proUser->id)->latest('id')->first();
        $this->assertSame('До конца тарифа остался 1 день', $ru->title);
        $this->assertStringContainsString('Pro', $ru->body);

        $this->assertSame(0, Announcement::where('user_id', $blocked->id)->count());

        // Qayta ishga tushsa takrorlanmaydi
        $this->artisan('subscriptions:remind-expiring')->assertSuccessful();
        $this->assertCount(2, ArraySmsSender::all());
        $this->assertSame(1, Announcement::where('user_id', $trialUser->id)->where('title', 'Tarif tugashiga 1 kun qoldi')->count());
    }

    public function test_reminder_is_scheduled_daily_at_9_tashkent_time(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'subscriptions:remind-expiring'));

        $this->assertNotNull($event);
        $this->assertSame('0 9 * * *', $event->expression);
        $this->assertSame('Asia/Tashkent', (string) $event->timezone);
    }
}
