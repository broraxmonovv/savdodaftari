<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Billing\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrialTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $phone = '+998901234567', string $locale = 'uz'): User
    {
        app()->setLocale($locale);
        [$user] = app(AuthService::class)->loginWithVerifiedOtp($phone, 'login', 'mobile');

        return $user->refresh();
    }

    public function test_new_user_gets_14_day_standard_trial_once(): void
    {
        $user = $this->register();

        $sub = $user->activeSubscription();
        $this->assertSame('standard', $sub->plan);
        $this->assertTrue($sub->is_trial);
        $this->assertEqualsWithDelta(14, now()->diffInDays($sub->expires_at), 0.01);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/auth/me')
            ->assertJsonPath('data.plan', 'standard')
            ->assertJsonPath('data.plan_is_trial', true)
            ->assertJsonPath('data.features.sales', true)
            ->assertJsonPath('data.features.pro', false);
        $this->getJson('/api/v1/sales')->assertOk();
        $this->getJson('/api/v1/billing/plan')
            ->assertJsonPath('data.is_trial', true)
            ->assertJsonPath('data.trial.days', 14)
            ->assertJsonPath('data.trial.active', true)
            ->assertJsonPath('data.trial.used', true)
            ->assertJsonPath('data.free.limits.customers', 30);

        // Xush kelibsiz bildirishnomasi
        $this->getJson('/api/v1/announcements')->assertJsonPath('data.0.title', 'Xush kelibsiz! 🎁');

        // Qayta kirish, hisobni o'chirib qayta ro'yxatdan o'tish — sinov qayta berilmaydi
        $this->register();
        $user->delete();
        $again = User::withTrashed()->find($user->id);
        $again->restore();
        $this->register();
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
    }

    public function test_trial_can_be_disabled_and_factory_users_get_none(): void
    {
        config()->set('savdodaftar.trial.days', 0);
        $user = $this->register('+998907654321');
        $this->assertNull($user->activeSubscription());
        $this->assertSame('free', $user->currentPlan());

        $this->assertNull(User::factory()->create()->activeSubscription());
    }

    public function test_after_trial_standard_features_close_but_the_rest_keep_working(): void
    {
        $user = $this->register();
        Sanctum::actingAs($user);

        $this->travel(15)->days();
        $user = $user->fresh();

        $this->assertSame('free', $user->currentPlan());
        $this->assertFalse($user->hasSalesAndInventory());

        Sanctum::actingAs($user);
        // Yopiq: savdo, ombor, Pro
        $this->getJson('/api/v1/sales')->assertStatus(403)->assertJsonPath('code', 'plan_required');
        $this->getJson('/api/v1/products')->assertStatus(403);
        $this->postJson('/api/v1/ai/voice', ['text' => 'salom'])->assertStatus(403);
        // Ishlaydi: mijoz, qarz, xarajat, 7 kunlik hisobot, tarif ma'lumoti
        $this->postJson('/api/v1/customers', ['name' => 'Ali'])->assertCreated();
        $this->getJson('/api/v1/debts')->assertOk();
        $this->getJson('/api/v1/expenses')->assertOk();
        $this->getJson('/api/v1/reports/overview?period=week')->assertOk();
        $this->getJson('/api/v1/billing/plan')
            ->assertJsonPath('data.plan', 'free')
            ->assertJsonPath('data.is_trial', false)
            ->assertJsonPath('data.trial.used', true)
            ->assertJsonPath('data.trial.active', false);
    }

    public function test_trial_notifications_are_sent_once_each(): void
    {
        $user = $this->register('+998901111111', 'ru');
        $this->assertSame(1, Announcement::where('user_id', $user->id)->count()); // welcome

        // Hali erta — hech narsa
        $this->artisan('trial:notify')->assertSuccessful();
        $this->assertSame(1, Announcement::where('user_id', $user->id)->count());

        // Tugashiga 2 kundan kam qoldi
        $this->travelTo(now()->addDays(12)->addHours(6));
        $this->artisan('trial:notify')->assertSuccessful();
        $this->artisan('trial:notify')->assertSuccessful(); // takroriy ishga tushirish — dublikat yo'q

        $ending = Announcement::where('user_id', $user->id)->where('title', 'Бесплатный период заканчивается')->get();
        $this->assertCount(1, $ending);
        $this->assertStringContainsString('12 000', $ending->first()->body);
        $this->assertStringContainsString('30 дней', $ending->first()->body);

        // Tugadi
        $this->travel(2)->days();
        $this->artisan('trial:notify')->assertSuccessful();
        $this->artisan('trial:notify')->assertSuccessful();

        $ended = Announcement::where('user_id', $user->id)->where('title', 'Бесплатный период закончился')->get();
        $this->assertCount(1, $ended);
        $this->assertSame('expired', $user->subscriptions()->first()->status);
        $this->assertSame(3, Announcement::where('user_id', $user->id)->count());

        // O'zbekcha foydalanuvchiga o'zbekcha matn
        $this->travelBack();
        $uz = $this->register('+998902222222', 'uz');
        $this->travel(13)->days();
        $this->artisan('trial:notify')->assertSuccessful();
        $this->assertTrue(Announcement::where('user_id', $uz->id)->where('title', 'Bepul sinov tugayapti')->exists());
    }

    public function test_paying_standard_during_trial_extends_by_30_days_from_trial_end(): void
    {
        $user = $this->register();
        Sanctum::actingAs($user);
        $trialEnd = $user->activeSubscription()->expires_at->copy();

        // Sinovda turib Standartga to'lash mumkin (oddiy holda "already_standard" bo'lardi)
        $payment = $this->postJson('/api/v1/billing/checkout', ['plan' => 'standard', 'provider' => 'payme'])
            ->assertCreated()->json('data.payment');
        app(BillingService::class)->activate(\App\Models\Payment::where('order_id', $payment['order_id'])->first());

        $sub = $user->fresh()->activeSubscription();
        $this->assertFalse($sub->is_trial);
        $this->assertEqualsWithDelta(30, $trialEnd->diffInDays($sub->expires_at), 0.01);
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());

        $this->getJson('/api/v1/billing/plan')->assertJsonPath('data.is_trial', false)->assertJsonPath('data.plan', 'standard');

        // Endi pullik Standart faol — qayta sotib bo'lmaydi; Pro mumkin
        $this->postJson('/api/v1/billing/checkout', ['plan' => 'standard', 'provider' => 'payme'])
            ->assertStatus(422)->assertJsonPath('code', 'already_standard');
        $this->postJson('/api/v1/billing/checkout', ['plan' => 'pro', 'provider' => 'click'])->assertCreated();

        // Pullik obuna uchun sinov xabarlari yuborilmaydi
        $this->travel(13)->days();
        $this->artisan('trial:notify')->assertSuccessful();
        $this->assertSame(1, Announcement::where('user_id', $user->id)->count()); // faqat welcome
    }

    public function test_trial_user_can_pay_for_standard_with_bonus_and_go_pro(): void
    {
        $user = $this->register();
        $user->forceFill(['bonus_balance' => 20000])->save();
        Sanctum::actingAs($user->refresh());

        $this->postJson('/api/v1/bonuses/pay-plan', ['plan' => 'standard'])->assertCreated();
        $this->assertFalse($user->fresh()->activeSubscription()->is_trial);
    }
}
