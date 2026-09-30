<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\User;
use App\Services\Sms\ArraySmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BannerAvatarSmsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['phone' => '+998900000001']);
        $admin->forceFill(['is_admin' => true, 'admin_password' => 'secret-pass-1'])->save();

        return $admin->refresh();
    }

    public function test_admin_manages_banners(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->post('/admin/login', ['phone' => $admin->phone, 'password' => 'secret-pass-1'])->assertRedirect();

        $this->post('/admin/banners', [
            'title' => 'Aksiya', 'url' => 'https://example.uz/aksiya', 'days' => 10,
            'image' => UploadedFile::fake()->image('b.jpg', 1200, 500),
        ])->assertSessionHasNoErrors();

        $banner = Banner::first();
        $this->assertNotNull($banner);
        Storage::disk('public')->assertExists($banner->image_path);
        $this->assertTrue($banner->ends_at->isFuture());

        // Xavfli havola rad etiladi
        $this->post('/admin/banners', [
            'title' => 'X', 'url' => 'javascript:alert(1)', 'image' => UploadedFile::fake()->image('c.jpg'),
        ])->assertSessionHasErrors('url');

        $this->get('/admin/banners')->assertOk()->assertSee('Aksiya');

        // O'chirib qo'yish, muddatni uzaytirish va o'chirish
        $this->put("/admin/banners/{$banner->id}", ['is_active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($banner->fresh()->is_active);

        $this->put("/admin/banners/{$banner->id}", ['days' => 60]);
        $this->assertTrue($banner->fresh()->ends_at->gt(now()->addDays(59)));

        $this->delete("/admin/banners/{$banner->id}");
        $this->assertNull(Banner::find($banner->id));
        Storage::disk('public')->assertMissing($banner->image_path);
    }

    public function test_api_returns_only_visible_banners_and_counts_clicks(): void
    {
        $live = Banner::create(['title' => 'Aksiya', 'url' => 'https://example.uz/aksiya', 'image_path' => 'banners/a.jpg', 'is_active' => true, 'starts_at' => now()]);
        Banner::create(['title' => 'Eski', 'url' => 'https://old.uz', 'image_path' => 'banners/old.jpg', 'is_active' => true,
            'starts_at' => now()->subDays(10), 'ends_at' => now()->subDay()]);
        Banner::create(['title' => 'Kelgusi', 'url' => 'https://next.uz', 'image_path' => 'banners/n.jpg', 'is_active' => true, 'starts_at' => now()->addDay()]);
        Banner::create(['title' => 'Oʻchiq', 'url' => 'https://off.uz', 'image_path' => 'banners/off.jpg', 'is_active' => false]);

        $this->getJson('/api/v1/banners')->assertStatus(401);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/banners')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Aksiya')
            ->assertJsonPath('data.0.url', 'https://example.uz/aksiya');

        $this->postJson("/api/v1/banners/{$live->id}/click")->assertOk();
        $this->assertSame(1, $live->fresh()->clicks);
    }

    public function test_user_uploads_and_removes_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/avatar', ['avatar' => UploadedFile::fake()->create('x.pdf', 10)])->assertStatus(422);

        $path = $this->postJson('/api/v1/auth/avatar', ['avatar' => UploadedFile::fake()->image('me.png', 300, 300)])
            ->assertOk()->assertJsonPath('data.avatar_url', fn ($v) => $v !== null)
            ->json('data.avatar_url');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($user->fresh()->avatar_path);

        $this->getJson('/api/v1/auth/me')->assertJsonPath('data.avatar_url', $path);

        $old = $user->fresh()->avatar_path;
        $this->postJson('/api/v1/auth/avatar', ['avatar' => UploadedFile::fake()->image('new.jpg')])->assertOk();
        Storage::disk('public')->assertMissing($old);

        $this->deleteJson('/api/v1/auth/avatar')->assertOk()->assertJsonPath('data.avatar_url', null);
        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_debt_sms_reminders_overdue_and_due_soon(): void
    {
        ArraySmsSender::flush();
        $owner = User::factory()->create(['name' => 'Bro', 'shop_name' => 'Bozor do\'koni', 'locale' => 'uz']);
        $ali = Customer::factory()->create(['user_id' => $owner->id, 'name' => 'Ali', 'phone' => '+998901112233']);
        $vali = Customer::factory()->create(['user_id' => $owner->id, 'name' => 'Vali', 'phone' => '+998907778899']);
        $noPhone = Customer::factory()->create(['user_id' => $owner->id, 'name' => 'Telefonsiz', 'phone' => null]);

        $mk = fn (Customer $c, int $amount, string $due, int $paid = 0) => Debt::create([
            'user_id' => $owner->id, 'customer_id' => $c->id, 'amount' => $amount, 'paid_amount' => $paid,
            'due_date' => $due, 'status' => $paid > 0 ? 'partial' : 'open', 'issued_at' => now(),
        ]);

        // Ali: ikkita muddati o'tgan qarz — bitta SMS, jami summa
        $mk($ali, 100000, today()->subDays(3)->toDateString());
        $mk($ali, 50000, today()->subDay()->toDateString(), 10000);
        // Vali: ertaga tugaydi
        $mk($vali, 70000, today()->addDay()->toDateString());
        // Telefonsiz va to'langan qarz — SMS yo'q
        $mk($noPhone, 5000, today()->subDay()->toDateString());
        Debt::create(['user_id' => $owner->id, 'customer_id' => $ali->id, 'amount' => 1000, 'paid_amount' => 1000,
            'due_date' => today()->subDays(5), 'status' => 'paid', 'issued_at' => now()]);

        $this->artisan('debts:sms-reminders')->assertSuccessful();

        $messages = ArraySmsSender::all();
        $this->assertCount(2, $messages);

        $overdue = collect($messages)->firstWhere('phone', '+998901112233');
        $this->assertStringContainsString('Assalomu alaykum Ali', $overdue['message']);
        $this->assertStringContainsString('Bozor do\'koni', $overdue['message']);
        $this->assertStringContainsString('140 000', $overdue['message']);
        $this->assertStringContainsString('muddati o\'tib ketti', $overdue['message']);

        $soon = collect($messages)->firstWhere('phone', '+998907778899');
        $this->assertStringContainsString('ertaga tugaydi', $soon['message']);
        $this->assertStringContainsString('70 000', $soon['message']);

        // Xuddi shu kuni qayta ishga tushirilsa — takroriy SMS yo'q
        $this->artisan('debts:sms-reminders')->assertSuccessful();
        $this->assertCount(2, ArraySmsSender::all());

        // 7 kundan keyin muddati o'tgan qarz uchun yana eslatma
        $this->travel(7)->days();
        $this->artisan('debts:sms-reminders')->assertSuccessful();
        $this->assertGreaterThan(2, count(ArraySmsSender::all()));
    }

    public function test_sms_reminders_respect_owner_switch_and_locale(): void
    {
        ArraySmsSender::flush();
        $off = User::factory()->create(['sms_reminders' => false]);
        $ru = User::factory()->create(['name' => 'Иван', 'shop_name' => 'Магазин', 'locale' => 'ru']);

        foreach ([$off, $ru] as $owner) {
            $c = Customer::factory()->create(['user_id' => $owner->id, 'phone' => '+99890'.random_int(1000000, 9999999)]);
            Debt::create(['user_id' => $owner->id, 'customer_id' => $c->id, 'amount' => 1000, 'paid_amount' => 0,
                'due_date' => today()->subDay(), 'status' => 'open', 'issued_at' => now()]);
        }

        $this->artisan('debts:sms-reminders')->assertSuccessful();

        $this->assertCount(1, ArraySmsSender::all());
        $this->assertStringContainsString('Здравствуйте', ArraySmsSender::last()['message']);
    }

    public function test_uploaded_images_are_served_even_without_storage_link(): void
    {
        Storage::fake('public');
        config()->set('app.url', 'http://localhost'); // noto'g'ri APP_URL ta'sir qilmasligi kerak

        $file = UploadedFile::fake()->image('b.jpg', 600, 250);
        $path = $file->store('banners', 'public');
        $banner = Banner::create(['title' => 'T', 'url' => 'https://a.uz', 'image_path' => $path, 'is_active' => true]);

        // URL joriy so'rov manzilidan yasaladi (host va port bilan)
        $url = $banner->image_url;
        $this->assertStringEndsWith('/media/'.$path, $url);

        $response = $this->get('/media/'.$path)->assertOk();
        $this->assertStringStartsWith('image/', $response->headers->get('Content-Type'));

        // Faqat rasm papkalari ochiq; boshqa yo'llar va yo'q fayllar 404
        $this->get('/media/backups/secret.json')->assertNotFound();
        $this->get('/media/banners/yoq.jpg')->assertNotFound();
        $this->get('/media/banners/../../.env')->assertNotFound();
    }
}
