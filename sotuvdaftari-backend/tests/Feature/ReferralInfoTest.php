<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralInfoTest extends TestCase
{
    use RefreshDatabase;

    private function payFor(User $user, string $plan = 'standard'): Payment
    {
        $payment = app(BillingService::class)->checkout($user, $plan, 'payme');

        return app(BillingService::class)->activate($payment);
    }

    public function test_referral_code_link_and_stats(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $data = $this->getJson('/api/v1/referral')->assertOk()
            ->assertJsonPath('data.percent', 10)
            ->assertJsonPath('data.invited_count', 0)
            ->json('data');

        $this->assertSame(8, strlen($data['code']));
        $this->assertStringContainsString($data['code'], $data['link']);

        // Kod barqaror
        $this->getJson('/api/v1/referral')->assertJsonPath('data.code', $data['code']);
    }

    public function test_profile_attaches_referrer_once_and_validates_code(): void
    {
        $owner = User::factory()->create();
        $code = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/referral')->json('data.code');

        $friend = User::factory()->create(['name' => null]);
        Sanctum::actingAs($friend);

        $this->putJson('/api/v1/auth/profile', ['name' => 'Ali', 'referral_code' => 'YOQYOQYO'])
            ->assertStatus(422)->assertJsonPath('code', 'referral_invalid');
        $this->assertNull($friend->fresh()->referred_by_id);

        $this->putJson('/api/v1/auth/profile', ['name' => 'Ali', 'referral_code' => strtolower($code)])
            ->assertOk()->assertJsonPath('data.has_referrer', true);
        $this->assertSame($owner->id, $friend->fresh()->referred_by_id);

        // O'z kodini kiritib bo'lmaydi
        Sanctum::actingAs($owner);
        $this->putJson('/api/v1/auth/profile', ['name' => 'Owner', 'referral_code' => $code])
            ->assertStatus(422)->assertJsonPath('code', 'referral_self');
    }

    public function test_referrer_gets_ten_percent_of_every_payment_and_reversal(): void
    {
        $owner = User::factory()->create();
        $friend = User::factory()->create(['referred_by_id' => $owner->id]);
        $stranger = User::factory()->create();

        $this->payFor($friend, 'standard');   // 12 000 -> 1 200
        $this->assertEquals(1200.0, (float) $owner->fresh()->bonus_balance);

        $pro = $this->payFor($friend, 'pro');  // 49 000 -> 4 900
        $this->assertEquals(6100.0, (float) $owner->fresh()->bonus_balance);

        // Takroriy activate — qayta bonus bermaydi
        app(BillingService::class)->activate($pro);
        $this->assertEquals(6100.0, (float) $owner->fresh()->bonus_balance);

        // Taklifsiz foydalanuvchi to'lovi bonus bermaydi
        $this->payFor($stranger);
        $this->assertEquals(6100.0, (float) $owner->fresh()->bonus_balance);

        // Payme storno — bonus qaytariladi (idempotent)
        $pro->forceFill(['provider_state' => 2])->save();
        app(BillingService::class)->cancelPayme($pro->fresh(), 5, 1);
        app(BillingService::class)->cancelPayme($pro->fresh(), 5, 1);
        $this->assertEquals(1200.0, (float) $owner->fresh()->bonus_balance);

        Sanctum::actingAs($owner->refresh());
        $this->getJson('/api/v1/referral')
            ->assertJsonPath('data.invited_count', 1)
            ->assertJsonPath('data.paying_count', 1)
            ->assertJsonPath('data.earned_total', 6100);
        $this->getJson('/api/v1/bonuses')
            ->assertOk()
            ->assertJsonPath('meta.balance', 1200)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.type', 'reversal')
            ->assertJsonPath('data.0.amount', -4900);
        $this->getJson('/api/v1/auth/me')->assertJsonPath('data.bonus_balance', 1200);
    }

    public function test_currencies_come_from_cbu_and_are_cached_with_stale_fallback(): void
    {
        Cache::flush();
        Http::fake([
            'cbu.uz/*' => Http::sequence()
                ->push([
                    ['Ccy' => 'USD', 'CcyNm_UZ' => 'AQSH dollari', 'CcyNm_RU' => 'Доллар США', 'Nominal' => '1', 'Rate' => '12650.50', 'Diff' => '-12.3', 'Date' => '30.09.2026'],
                    ['Ccy' => 'AFN', 'Rate' => '1', 'Nominal' => '1'],
                    ['Ccy' => 'EUR', 'CcyNm_UZ' => 'EVRO', 'Nominal' => '1', 'Rate' => '14800', 'Diff' => '5', 'Date' => '30.09.2026'],
                ])
                ->push('xato', 500),
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/currencies')->assertOk()
            ->assertJsonPath('data.source', 'cbu.uz')
            ->assertJsonCount(2, 'data.rates')
            ->assertJsonPath('data.rates.0.code', 'USD')
            ->assertJsonPath('data.rates.0.rate', 12650.5)
            ->assertJsonPath('data.rates.1.code', 'EUR');

        // Kesh tufayli ikkinchi so'rov CBU'ga bormaydi
        $this->getJson('/api/v1/currencies')->assertJsonCount(2, 'data.rates');
        Http::assertSentCount(1);

        // Kesh eskirgach CBU ishlamasa oxirgi saqlangan kurs qaytadi
        Cache::forget('currency_rates.fresh');
        $this->getJson('/api/v1/currencies')->assertOk()->assertJsonCount(2, 'data.rates');
    }

    public function test_support_and_guides(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/support')->assertOk()
            ->assertJsonStructure(['data' => ['phone', 'telegram', 'email', 'working_hours']]);

        $this->getJson('/api/v1/guides')->assertOk()
            ->assertJsonPath('data.0.id', 'start')
            ->assertJsonPath('data.0.title', 'Ilovadan foydalanishni boshlash');

        $this->getJson('/api/v1/guides', ['Accept-Language' => 'ru'])
            ->assertJsonPath('data.0.title', 'Начало работы с приложением');
    }
}
