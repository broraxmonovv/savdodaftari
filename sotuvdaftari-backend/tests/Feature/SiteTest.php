<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['phone' => '+998900000001']);
        $admin->forceFill(['is_admin' => true, 'admin_password' => 'secret-pass-1'])->save();

        return $admin->refresh();
    }

    private function payload(array $over = []): array
    {
        return $over + [
            'name' => 'Karim Sotuvchi',
            'phone' => '90 123 45 67',
            'business_type' => 'shop',
            'message' => 'Ilova haqida so\'ramoqchiman',
            'consent' => 1,
        ];
    }

    public function test_landing_shows_service_info_and_db_plan_prices_in_both_languages(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Bozorchi uchun telefon ichidagi aqlli daftar')
            ->assertSee('12 000')->assertSee('49 000')
            ->assertSee('10%');

        Plan::where('key', 'standard')->update(['price' => 15000]);
        Plan::where('key', 'pro')->update(['is_active' => false]);

        $this->get('/?lang=ru')->assertOk()
            ->assertSee('Умная тетрадь для торговца')
            ->assertSee('15 000')->assertDontSee('49 000');

        // Til sessiyada saqlanadi
        $this->get('/')->assertSee('Умная тетрадь для торговца');

        // Yuklab olish tugmalari faqat havola berilganda
        $this->get('/?lang=uz')->assertDontSee('Android uchun');
        config()->set('savdodaftar.site.android_url', 'https://play.google.com/store/apps/details?id=x');
        $this->get('/')->assertSee('Android uchun');
    }

    public function test_lead_form_saves_normalized_phone_and_deduplicates(): void
    {
        $this->post('/lead', $this->payload())->assertRedirect()->assertSessionHas('lead_sent');

        $lead = Lead::first();
        $this->assertSame('+998901234567', $lead->phone);
        $this->assertSame('Karim Sotuvchi', $lead->name);
        $this->assertSame('new', $lead->status);
        $this->assertSame('uz', $lead->locale);

        // 10 daqiqa ichida o'sha raqamdan qayta ariza — saqlanmaydi, lekin foydalanuvchiga muvaffaqiyat ko'rsatiladi
        $this->post('/lead', $this->payload(['name' => 'Yana']))->assertSessionHas('lead_sent');
        $this->assertSame(1, Lead::count());

        // Boshqa formatlar
        $this->post('/lead', $this->payload(['phone' => '+998 (91) 222-33-44', 'lang' => 'ru']));
        $this->assertSame('+998912223344', Lead::latest('id')->first()->phone);
    }

    public function test_lead_form_validation_and_honeypot(): void
    {
        $this->post('/lead', $this->payload(['phone' => '12345']))->assertSessionHasErrors('phone');
        $this->post('/lead', $this->payload(['name' => '']))->assertSessionHasErrors('name');
        $this->post('/lead', $this->payload(['consent' => null]))->assertSessionHasErrors('consent');
        $this->post('/lead', $this->payload(['business_type' => 'xxx']))->assertSessionHasErrors('business_type');
        $this->assertSame(0, Lead::count());

        // Bot yashirin maydonni to'ldirsa — jim e'tiborsiz qoldiriladi
        $this->post('/lead', $this->payload(['website' => 'http://spam.example']))->assertSessionHas('lead_sent');
        $this->assertSame(0, Lead::count());
    }

    public function test_leads_are_visible_and_manageable_in_admin_panel(): void
    {
        $this->post('/lead', $this->payload());
        $known = User::factory()->create(['phone' => '+998901234567']);

        $this->get('/admin/leads')->assertRedirect('/admin/login');

        $this->actingAs($this->admin());
        $this->get('/admin')->assertOk()->assertSee('Yangi arizalar');
        $this->get('/admin/leads')->assertOk()
            ->assertSee('Karim Sotuvchi')->assertSee('+998901234567')
            ->assertSee("ilovada ro'yxatdan o'tgan", false);
        $this->get('/admin/leads?q=Karim')->assertSee('Karim Sotuvchi');
        $this->get('/admin/leads?q=YOQ')->assertDontSee('Karim Sotuvchi');
        $this->get('/admin/leads?status=done')->assertDontSee('Karim Sotuvchi');

        $id = Lead::first()->id;
        $this->put("/admin/leads/{$id}", ['status' => 'contacted', 'admin_note' => 'Qo\'ng\'iroq qilindi'])
            ->assertSessionHas('status');
        $lead = Lead::first();
        $this->assertSame('contacted', $lead->status);
        $this->assertNotNull($lead->processed_at);
        $this->get('/admin/leads?status=contacted')->assertSee('Karim Sotuvchi');

        $csv = $this->get('/admin/leads/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('Karim Sotuvchi', $csv);
        $this->assertStringContainsString('+998901234567', $csv);

        $this->delete("/admin/leads/{$id}")->assertSessionHas('status');
        $this->assertSame(0, Lead::count());
        unset($known);
    }
}
