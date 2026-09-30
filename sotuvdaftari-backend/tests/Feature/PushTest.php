<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PushTest extends TestCase
{
    use RefreshDatabase;

    private string $credentialsPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Test uchun haqiqiy RSA kalitli soxta service account
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->credentialsPath = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($this->credentialsPath, json_encode(['client_email' => 'svc@test.iam.gserviceaccount.com', 'private_key' => $pem]));

        config()->set('savdodaftar.push.fcm_project_id', 'bozorpro-test');
        config()->set('savdodaftar.push.fcm_credentials', $this->credentialsPath);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        @unlink($this->credentialsPath);
        parent::tearDown();
    }

    private function adminLogin(): void
    {
        $admin = User::factory()->create(['phone' => '+998900000001']);
        $admin->forceFill(['is_admin' => true, 'admin_password' => 'secret-pass-1'])->save();
        $this->actingAs($admin->refresh());
    }

    public function test_device_token_registration_moves_between_accounts_and_unregisters(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        Sanctum::actingAs($a);
        $this->postJson('/api/v1/devices', ['token' => 'tok-1', 'platform' => 'android'])->assertCreated();
        $this->postJson('/api/v1/devices', ['token' => 'tok-1', 'platform' => 'android'])->assertCreated();
        $this->postJson('/api/v1/devices', ['token' => 'tok-1', 'platform' => 'web'])->assertStatus(422);
        $this->assertSame(1, DeviceToken::count());

        // Telefon boshqa akkauntga kirdi — token unga o'tadi
        Sanctum::actingAs($b);
        $this->postJson('/api/v1/devices', ['token' => 'tok-1'])->assertCreated();
        $this->assertSame($b->id, DeviceToken::first()->user_id);

        $this->deleteJson('/api/v1/devices', ['token' => 'tok-1'])->assertOk();
        $this->assertSame(0, DeviceToken::count());
    }

    public function test_announcement_is_pushed_to_audience_devices_via_fcm(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'at-123']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'ok']),
        ]);

        $free = User::factory()->create();
        $std = User::factory()->standard()->create();
        $blocked = User::factory()->create();
        $blocked->forceFill(['blocked_at' => now()])->save();
        foreach ([[$free, 'tok-free'], [$std, 'tok-std'], [$blocked, 'tok-blocked']] as [$u, $t]) {
            DeviceToken::create(['user_id' => $u->id, 'token' => $t]);
        }

        $this->adminLogin();
        $this->post('/admin/announcements', ['title' => 'Aksiya', 'body' => 'Chegirma bor', 'audience' => 'all', 'push' => 1])
            ->assertSessionHas('status');

        $sent = Http::recorded(fn ($r) => str_contains($r->url(), 'messages:send'))
            ->map(fn ($pair) => $pair[0]->data()['message']['token'])->all();
        $this->assertEqualsCanonicalizing(['tok-free', 'tok-std'], $sent); // bloklangan olmaydi

        Http::assertSent(fn ($r) => str_contains($r->url(), 'bozorpro-test/messages:send')
            && $r->hasHeader('Authorization', 'Bearer at-123')
            && $r['message']['notification']['title'] === 'Aksiya'
            && $r['message']['data']['type'] === 'announcement');

        // OAuth token keshlangan: faqat bir marta olingan
        Http::assertSentCount(3); // 1 oauth + 2 fcm
    }

    public function test_push_is_skipped_when_unchecked(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        $this->adminLogin();

        $this->post('/admin/announcements', ['title' => 'Jim', 'body' => 'x', 'audience' => 'all', 'push' => 0]);
        \Illuminate\Support\Facades\Bus::assertNotDispatchedAfterResponse(\App\Jobs\SendAnnouncementPush::class);

        $this->post('/admin/announcements', ['title' => 'Ovozli', 'body' => 'x', 'audience' => 'all', 'push' => 1]);
        \Illuminate\Support\Facades\Bus::assertDispatchedAfterResponse(\App\Jobs\SendAnnouncementPush::class);
    }

    public function test_unregistered_token_is_removed_and_unconfigured_fcm_is_noop(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'at']),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNREGISTERED']], 404),
        ]);
        $user = User::factory()->create();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'dead']);

        $this->adminLogin();
        $this->post('/admin/announcements', ['title' => 'T', 'body' => 'B', 'audience' => 'user', 'phone' => $user->phone]);
        $this->assertSame(0, DeviceToken::count());

        // Sozlanmagan bo'lsa jim o'tadi
        config()->set('savdodaftar.push.fcm_project_id', null);
        DeviceToken::create(['user_id' => $user->id, 'token' => 'alive']);
        app(\App\Services\Push\PushService::class)->toUser($user, 'T2', 'B');
        $this->assertSame(1, DeviceToken::count()); // token o'chirilmadi, FCM'ga murojaat qilinmadi
    }

    public function test_daily_reminders_push_expiring_plan_and_overdue_debts(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'at']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'ok']),
        ]);

        $user = User::factory()->standard()->create();
        $user->subscriptions()->update(['expires_at' => now()->addDays(2)->addHours(12)]);
        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok']);

        $quiet = User::factory()->create();
        DeviceToken::create(['user_id' => $quiet->id, 'token' => 'tok-quiet']);

        $this->artisan('push:reminders')->assertSuccessful();

        $titles = Http::recorded(fn ($r) => str_contains($r->url(), 'messages:send'))
            ->map(fn ($pair) => $pair[0]->data()['message']['notification']['title'])->values()->all();
        $this->assertSame(['Tarif tugamoqda'], $titles);
    }
}
