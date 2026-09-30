<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Referral\BonusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::where('phone', '+998900000001')->first()
            ?? User::factory()->create(['name' => 'Admin', 'phone' => '+998900000001']);
        $admin->forceFill(['is_admin' => true, 'admin_password' => 'secret-pass-1'])->save();

        return $admin->refresh();
    }

    public function test_login_is_required_and_only_admins_can_enter(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('BozorPro Admin');

        // Noto'g'ri parol
        $this->admin();
        $this->post('/admin/login', ['phone' => '+998900000001', 'password' => 'wrong'])
            ->assertSessionHasErrors('phone');

        // Oddiy foydalanuvchi (admin emas) kira olmaydi
        $user = User::factory()->create(['phone' => '+998900000002']);
        $user->forceFill(['admin_password' => 'secret-pass-1'])->save();
        $this->post('/admin/login', ['phone' => '+998900000002', 'password' => 'secret-pass-1'])
            ->assertSessionHasErrors('phone');

        // Telefon formati bo'sh joylar bilan ham ishlaydi
        $this->post('/admin/login', ['phone' => '998 90 000 00 01', 'password' => 'secret-pass-1'])
            ->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertSee('Dashboard');

        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_dashboard_users_and_user_detail_show_app_data(): void
    {
        $user = User::factory()->standard()->create(['name' => 'Vali Sotuvchi', 'phone' => '+998911112233', 'shop_name' => 'Vali Market']);
        Customer::create(['user_id' => $user->id, 'name' => 'Mijoz Bir', 'phone' => '+998900001111', 'balance' => 0]);
        Product::factory()->for($user)->create(['name' => 'Futbolka']);

        $this->actingAs($this->admin());

        $this->get('/admin')->assertOk()->assertSee('Foydalanuvchilar')->assertSee('Tushum');

        $this->get('/admin/users?q=Vali')->assertOk()->assertSee('Vali Sotuvchi')->assertSee('standard');
        $this->get('/admin/users?q=YOQ')->assertOk()->assertDontSee('Vali Sotuvchi');
        $this->get('/admin/users?plan=free')->assertOk()->assertDontSee('Vali Sotuvchi');
        $this->get('/admin/users?plan=standard')->assertOk()->assertSee('Vali Sotuvchi');

        $this->get("/admin/users/{$user->id}")->assertOk()
            ->assertSee('Vali Market')->assertSee('Mijoz Bir')->assertSee('Futbolka');

        // Adminning o'zi foydalanuvchilar ro'yxatida ko'rinmaydi
        $this->get('/admin/users')->assertDontSee('+998900000001');
    }

    public function test_block_denies_api_keeps_support_open_then_unblock(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/auth/me')->assertOk();

        $this->actingAs($this->admin())
            ->post("/admin/users/{$user->id}/block", ['reason' => 'Spam'])
            ->assertSessionHas('status');

        $this->assertTrue($user->fresh()->isBlocked());

        // Token saqlanadi, lekin har bir so'rov 403 account_blocked (sabab bilan) qaytaradi
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/auth/me')
            ->assertStatus(403)->assertJsonPath('code', 'account_blocked')->assertJsonPath('meta.reason', 'Spam');
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/customers')->assertStatus(403);

        // Qo'llab-quvvatlash kontaktlari bloklangan foydalanuvchiga ham (hatto tokensiz) ochiq
        $this->app['auth']->forgetGuards();
        $this->withHeaders([])->getJson('/api/v1/support')->assertOk();

        $this->actingAs($this->admin()->refresh())
            ->post("/admin/users/{$user->id}/unblock")->assertSessionHas('status');
        $this->assertFalse($user->fresh()->isBlocked());
    }

    public function test_admin_can_grant_plan_manually(): void
    {
        $user = User::factory()->create();
        $this->actingAs($this->admin())
            ->post("/admin/users/{$user->id}/grant-plan", ['plan' => 'pro', 'days' => 10])
            ->assertSessionHas('status');

        $this->assertTrue($user->fresh()->isPro());

        // Shu tarif faol bo'lsa muddat uzayadi, yangi obuna yaratilmaydi
        $this->post("/admin/users/{$user->id}/grant-plan", ['plan' => 'pro', 'days' => 5]);
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
        $this->assertGreaterThan(14.5, now()->diffInDays($user->activeSubscription()->expires_at, false));
    }

    public function test_plan_prices_are_stored_in_db_and_editable(): void
    {
        $this->assertSame(12000, Plan::where('key', 'standard')->value('price'));
        $this->assertSame(49000, Plan::where('key', 'pro')->value('price'));

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/billing/plan')->assertJsonPath('data.plans.0.price', 12000);

        $this->actingAs($this->admin())
            ->put('/admin/plans/standard', ['price' => 15000, 'days' => 45, 'is_active' => 1])
            ->assertSessionHas('status');

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/billing/plan')
            ->assertJsonPath('data.plans.0.price', 15000)
            ->assertJsonPath('data.plans.0.days', 45);
        $this->postJson('/api/v1/billing/checkout', ['plan' => 'standard', 'provider' => 'payme'])
            ->assertCreated()->assertJsonPath('data.payment.amount', 15000);

        // Validatsiya
        $this->actingAs($this->admin());
        $this->put('/admin/plans/pro', ['price' => 10, 'days' => 30])->assertSessionHasErrors('price');

        // Faol bo'lmagan tarif sotuvdan olinadi
        $this->put('/admin/plans/pro', ['price' => 49000, 'days' => 30]);   // is_active yuborilmadi -> o'chdi
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/billing/plan')->assertJsonCount(1, 'data.plans');
        $this->postJson('/api/v1/billing/checkout', ['plan' => 'pro', 'provider' => 'payme'])
            ->assertStatus(422)->assertJsonPath('code', 'plan_unavailable');
    }

    public function test_announcements_reach_the_right_users(): void
    {
        $free = User::factory()->create();
        $standard = User::factory()->standard()->create();

        $this->actingAs($this->admin());
        $this->post('/admin/announcements', ['title' => 'Hammaga', 'body' => 'Salom', 'audience' => 'all'])
            ->assertSessionHas('status');
        $this->post('/admin/announcements', ['title' => 'Standartlarga', 'body' => 'Aksiya', 'audience' => 'plan', 'plan' => 'standard']);
        $this->post('/admin/announcements', ['title' => 'Shaxsiy', 'body' => 'Sizga', 'audience' => 'user', 'phone' => $free->phone]);
        $this->post('/admin/announcements', ['title' => 'Xato', 'body' => 'x', 'audience' => 'user', 'phone' => '+998000000000'])
            ->assertSessionHasErrors('phone');
        $this->get('/admin/announcements')->assertOk()->assertSee('Standartlarga');

        Sanctum::actingAs($free);
        $list = $this->getJson('/api/v1/announcements')->assertOk()
            ->assertJsonPath('meta.unread_count', 2)->json('data');
        $this->assertEqualsCanonicalizing(['Hammaga', 'Shaxsiy'], array_column($list, 'title'));

        $id = $list[0]['id'];
        $this->postJson("/api/v1/announcements/{$id}/read")->assertOk();
        $this->getJson('/api/v1/announcements')->assertJsonPath('meta.unread_count', 1);
        $this->postJson('/api/v1/announcements/read-all')->assertOk();
        $this->getJson('/api/v1/announcements')->assertJsonPath('meta.unread_count', 0);

        Sanctum::actingAs($standard);
        $titles = array_column($this->getJson('/api/v1/announcements')->json('data'), 'title');
        $this->assertEqualsCanonicalizing(['Hammaga', 'Standartlarga'], $titles);

        // Boshqa foydalanuvchining shaxsiy e'lonini o'qilgan deb belgilab bo'lmaydi
        $personal = Announcement::where('title', 'Shaxsiy')->value('id');
        $this->postJson("/api/v1/announcements/{$personal}/read")->assertStatus(404);
    }

    public function test_withdrawals_can_be_processed_from_panel(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['bonus_balance' => 30000])->save();
        $w1 = app(BonusService::class)->requestWithdrawal($user->refresh(), 10000, '4111111111111111', 'ALI');
        $w2 = app(BonusService::class)->requestWithdrawal($user->refresh(), 10000, '4111111111111111', null);

        $this->actingAs($this->admin());
        $this->get('/admin/withdrawals')->assertOk()->assertSee('4111 1111 1111 1111');

        $this->post("/admin/withdrawals/{$w1->id}/paid", ['note' => 'OK'])->assertSessionHas('status');
        $this->post("/admin/withdrawals/{$w2->id}/reject", ['note' => 'Xato'])->assertSessionHas('status');
        $this->post("/admin/withdrawals/{$w2->id}/reject")->assertSessionHasErrors('withdrawal');

        $this->assertSame('paid', Withdrawal::find($w1->id)->status);
        $this->assertEquals(20000.0, (float) $user->fresh()->bonus_balance); // 30000 - 10000 (to'landi); 10000 qaytdi
    }

    public function test_admin_grant_command_sets_password(): void
    {
        $user = User::factory()->create(['phone' => '+998933334455']);

        $this->artisan('admin:grant', ['phone' => '+998933334455', '--password' => 'short'])->assertFailed();
        $this->artisan('admin:grant', ['phone' => '+998933334455', '--password' => 'long-enough-1'])->assertSuccessful();

        $this->assertTrue($user->fresh()->is_admin);
        $this->post('/admin/login', ['phone' => '+998933334455', 'password' => 'long-enough-1'])->assertRedirect('/admin');
    }
}
