<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BonusSpendingTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_CARD = '4111111111111111';

    private function userWithBonus(float $bonus, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->forceFill(['bonus_balance' => $bonus])->save();

        return $user->refresh();
    }

    public function test_plan_can_be_paid_from_bonus_balance(): void
    {
        $user = $this->userWithBonus(20000);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/bonuses/pay-plan', ['plan' => 'pro'])
            ->assertStatus(422)->assertJsonPath('code', 'insufficient_bonus');

        $this->postJson('/api/v1/bonuses/pay-plan', ['plan' => 'standard'])
            ->assertCreated()
            ->assertJsonPath('data.plan', 'standard')
            ->assertJsonPath('data.balance', 8000)
            ->assertJsonPath('data.payment.plan', 'standard');

        $this->assertTrue($user->fresh()->hasSalesAndInventory());
        $this->getJson('/api/v1/sales')->assertOk();

        // Qayta sotib olib bo'lmaydi
        $this->postJson('/api/v1/bonuses/pay-plan', ['plan' => 'standard'])
            ->assertStatus(422)->assertJsonPath('code', 'already_standard');

        $this->getJson('/api/v1/bonuses')
            ->assertJsonPath('meta.balance', 8000)
            ->assertJsonPath('data.0.type', 'plan_payment')
            ->assertJsonPath('data.0.amount', -12000)
            ->assertJsonPath('data.0.plan', 'standard');
    }

    public function test_bonus_paid_plan_does_not_reward_referrer(): void
    {
        $owner = $this->userWithBonus(0);
        $friend = $this->userWithBonus(50000, ['referred_by_id' => $owner->id]);
        Sanctum::actingAs($friend);

        $this->postJson('/api/v1/bonuses/pay-plan', ['plan' => 'standard'])->assertCreated();

        $this->assertEquals(0.0, (float) $owner->fresh()->bonus_balance);
    }

    public function test_withdrawal_reserves_balance_and_admin_marks_paid(): void
    {
        Http::fake();
        config()->set('savdodaftar.admin.telegram_bot_token', 'tok');
        config()->set('savdodaftar.admin.telegram_chat_id', '42');

        $user = $this->userWithBonus(30000);
        Sanctum::actingAs($user);

        // Validatsiya
        $this->postJson('/api/v1/withdrawals', ['amount' => 5000, 'card_number' => self::VALID_CARD])
            ->assertStatus(422)->assertJsonPath('code', 'withdrawal_below_minimum');
        $this->postJson('/api/v1/withdrawals', ['amount' => 50000, 'card_number' => self::VALID_CARD])
            ->assertStatus(422)->assertJsonPath('code', 'insufficient_bonus');
        $this->postJson('/api/v1/withdrawals', ['amount' => 20000, 'card_number' => '1234567890123456'])
            ->assertStatus(422)->assertJsonPath('code', 'validation_failed');
        $this->postJson('/api/v1/withdrawals', ['amount' => 20000, 'card_number' => '4111'])
            ->assertStatus(422);

        $this->postJson('/api/v1/withdrawals', [
            'amount' => 20000,
            'card_number' => '4111 1111 1111 1111',
            'card_holder' => 'ALI VALIYEV',
        ])->assertCreated()
            ->assertJsonPath('data.withdrawal.status', 'pending')
            ->assertJsonPath('data.withdrawal.card', '4111 **** **** 1111')
            ->assertJsonPath('data.balance', 10000);

        // Karta raqami bazada shifrlangan, javobda to'liq chiqmaydi
        $this->assertStringNotContainsString(self::VALID_CARD, (string) \DB::table('withdrawals')->value('card_number'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.telegram.org/bottok/sendMessage')
            && str_contains($r['text'], self::VALID_CARD));

        $this->getJson('/api/v1/withdrawals')
            ->assertOk()
            ->assertJsonPath('data.0.amount', 20000)
            ->assertJsonMissingPath('data.0.card_number');

        // Oddiy foydalanuvchi admin endpointiga kira olmaydi
        $this->getJson('/api/v1/admin/withdrawals')->assertStatus(403);

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Sanctum::actingAs($admin->refresh());

        $id = $this->getJson('/api/v1/admin/withdrawals?status=pending')
            ->assertOk()
            ->assertJsonPath('data.0.card_number', self::VALID_CARD)
            ->assertJsonPath('data.0.user.phone', $user->phone)
            ->json('data.0.id');

        $this->postJson("/api/v1/admin/withdrawals/{$id}/paid", ['note' => 'Uzcard orqali'])
            ->assertOk()->assertJsonPath('data.status', 'paid');

        // Qayta ko'rib chiqib bo'lmaydi; balans o'zgarmaydi
        $this->postJson("/api/v1/admin/withdrawals/{$id}/reject")->assertStatus(422)
            ->assertJsonPath('code', 'withdrawal_already_processed');
        $this->assertEquals(10000.0, (float) $user->fresh()->bonus_balance);
    }

    public function test_rejected_withdrawal_refunds_balance_once(): void
    {
        $user = $this->userWithBonus(15000);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/withdrawals', ['amount' => 15000, 'card_number' => self::VALID_CARD])->assertCreated();
        $this->assertEquals(0.0, (float) $user->fresh()->bonus_balance);

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Sanctum::actingAs($admin->refresh());

        $id = Withdrawal::first()->id;
        $this->postJson("/api/v1/admin/withdrawals/{$id}/reject", ['note' => 'Karta noto\'g\'ri'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->postJson("/api/v1/admin/withdrawals/{$id}/reject")->assertStatus(422);

        $this->assertEquals(15000.0, (float) $user->fresh()->bonus_balance);

        Sanctum::actingAs($user->refresh());
        $this->getJson('/api/v1/bonuses')
            ->assertJsonPath('meta.balance', 15000)
            ->assertJsonPath('data.0.type', 'withdrawal_refund');
    }
}
