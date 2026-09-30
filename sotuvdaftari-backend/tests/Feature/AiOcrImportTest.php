<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Debt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiOcrImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.anthropic.key', 'test-key');
        $this->user = User::factory()->pro()->create();
        Sanctum::actingAs($this->user);
    }

    private function fakeClaude(string $text): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => $text]],
        ])]);
    }

    private function upload()
    {
        return $this->post('/api/v1/ai/ocr-import', [
            'image' => UploadedFile::fake()->image('daftar.jpg', 600, 800),
        ], ['Accept' => 'application/json']);
    }

    public function test_ocr_is_pro_only_and_needs_configuration(): void
    {
        Sanctum::actingAs(User::factory()->standard()->create());
        $this->upload()->assertStatus(403)->assertJsonPath('code', 'plan_required');

        Sanctum::actingAs($this->user);
        config()->set('services.anthropic.key', null);
        $this->upload()->assertStatus(503)->assertJsonPath('code', 'ai_not_configured');
    }

    public function test_extract_returns_cleaned_items_without_writing_anything(): void
    {
        Customer::create(['user_id' => $this->user->id, 'name' => 'Ali Valiyev', 'phone' => '+998901112233', 'balance' => 0]);

        $this->fakeClaude("Mana natija:\n```json\n".json_encode(['items' => [
            ['name' => 'Ali Valiyev', 'amount' => '150 000', 'phone' => null, 'note' => null, 'uncertain' => false],
            ['name' => 'Karim', 'amount' => 75000, 'phone' => '90 123 45 67', 'note' => 'mart oyi', 'uncertain' => true],
            ['name' => '', 'amount' => 5000],
            ['name' => 'Nol', 'amount' => 0],
            ['name' => 'Али Валийев', 'amount' => 10000],
        ]])."\n```");

        $response = $this->upload()->assertOk()
            ->assertJsonPath('data.count', 3)
            ->assertJsonPath('data.uncertain_count', 1)
            ->assertJsonPath('data.items.0.amount', 150000)
            ->assertJsonPath('data.items.0.existing_customer.name', 'Ali Valiyev')
            ->assertJsonPath('data.items.1.phone', '+998901234567')
            ->assertJsonPath('data.items.1.uncertain', true)
            ->assertJsonPath('data.items.1.existing_customer', null)
            // Kirillcha ism lotincha mavjud mijozga moslashadi
            ->assertJsonPath('data.items.2.existing_customer.name', 'Ali Valiyev');
        unset($response);

        // Hech narsa yozilmadi (TZ 34: tasdiqlamasdan moliyaviy amal yo'q)
        $this->assertSame(0, Debt::count());
        $this->assertSame(1, Customer::count());

        Http::assertSent(fn ($r) => $r->url() === 'https://api.anthropic.com/v1/messages'
            && $r->hasHeader('x-api-key', 'test-key')
            && $r['messages'][0]['content'][0]['type'] === 'image'
            && $r['messages'][0]['content'][0]['source']['media_type'] === 'image/jpeg');
    }

    public function test_extract_handles_bad_model_output_and_invalid_upload(): void
    {
        $this->fakeClaude('Kechirasiz, rasmni o\'qiy olmadim.');
        $this->upload()->assertStatus(502)->assertJsonPath('code', 'ai_failed');

        Http::fake(['api.anthropic.com/*' => Http::response('xato', 500)]);
        $this->upload()->assertStatus(502);

        $this->post('/api/v1/ai/ocr-import', ['image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->postJson('/api/v1/ai/ocr-import', [])->assertStatus(422);
    }

    public function test_confirm_creates_customers_and_debts_atomically(): void
    {
        $ali = Customer::create(['user_id' => $this->user->id, 'name' => 'Ali Valiyev', 'phone' => '+998901112233', 'balance' => 10000]);

        $this->postJson('/api/v1/ai/ocr-import/confirm', ['items' => [
            ['name' => 'Ali Valiyev', 'amount' => 150000],
            ['name' => 'Karim', 'amount' => 75000, 'phone' => '90 123 45 67', 'note' => 'mart'],
            ['name' => 'Karim', 'amount' => 25000, 'phone' => '+998901234567'],  // xuddi shu mijoz — ikki marta yaratilmaydi
        ]])->assertCreated()
            ->assertJsonPath('data.customers_created', 1)
            ->assertJsonPath('data.debts_created', 3)
            ->assertJsonPath('data.total', 250000);

        $this->assertSame(2, Customer::count());
        $this->assertSame(3, Debt::count());
        $this->assertEquals(160000.0, (float) $ali->fresh()->balance);
        $karim = Customer::where('name', 'Karim')->first();
        $this->assertEquals(100000.0, (float) $karim->balance);
        $this->assertSame('+998901234567', $karim->phone);

        // Validatsiya xatosi — hech narsa yozilmaydi
        $before = Debt::count();
        $this->postJson('/api/v1/ai/ocr-import/confirm', ['items' => [['name' => 'Yangi', 'amount' => 1000], ['name' => 'Xato', 'amount' => -5]]])
            ->assertStatus(422);
        $this->postJson('/api/v1/ai/ocr-import/confirm', ['items' => []])->assertStatus(422);
        $this->assertSame($before, Debt::count());
        $this->assertSame(2, Customer::count());

        // Pro emas
        Sanctum::actingAs(User::factory()->standard()->create());
        $this->postJson('/api/v1/ai/ocr-import/confirm', ['items' => [['name' => 'X', 'amount' => 1]]])->assertStatus(403);
    }
}
