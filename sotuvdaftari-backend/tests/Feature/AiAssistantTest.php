<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.anthropic.key', 'test-key');
        $this->user = User::factory()->pro()->create(['shop_name' => 'Vali Market']);
        Sanctum::actingAs($this->user);
    }

    private function ask(string $message, array $extra = [])
    {
        return $this->postJson('/api/v1/ai/assistant', ['message' => $message] + $extra);
    }

    public function test_assistant_is_pro_only_and_needs_configuration(): void
    {
        Sanctum::actingAs(User::factory()->standard()->create());
        $this->ask('Bugun qancha foyda qildim?')->assertStatus(403)->assertJsonPath('code', 'plan_required');

        Sanctum::actingAs($this->user);
        config()->set('services.anthropic.key', null);
        $this->ask('salom')->assertStatus(503)->assertJsonPath('code', 'ai_not_configured');

        config()->set('services.anthropic.key', 'k');
        $this->ask('')->assertStatus(422);
    }

    public function test_model_tool_calls_are_executed_with_real_data_and_answer_returned(): void
    {
        // Foydalanuvchi ma'lumotlari + boshqa foydalanuvchiniki (ko'rinmasligi kerak)
        Customer::create(['user_id' => $this->user->id, 'name' => 'Ali Valiyev', 'phone' => '+998901112233', 'balance' => 250000]);
        $other = User::factory()->create();
        Customer::create(['user_id' => $other->id, 'name' => 'Ali Begona', 'phone' => '+998900000000', 'balance' => 999]);
        Product::factory()->for($this->user)->create(['name' => 'Shim', 'stock' => 1, 'min_stock' => 5]);

        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push([
                'stop_reason' => 'tool_use',
                'content' => [
                    ['type' => 'text', 'text' => 'Tekshiraman'],
                    ['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'customer_debt', 'input' => ['name' => 'Ali']],
                    ['type' => 'tool_use', 'id' => 'tu_2', 'name' => 'low_stock', 'input' => (object) []],
                ],
            ])
            ->push([
                'stop_reason' => 'end_turn',
                'content' => [['type' => 'text', 'text' => "Ali Valiyevning qarzi 250 000 so'm. Shim kam qolgan."]],
            ]),
        ]);

        $this->ask('Ali qancha qarz va nima kam qoldi?', ['history' => [
            ['role' => 'user', 'content' => 'Salom'],
            ['role' => 'assistant', 'content' => 'Assalomu alaykum!'],
        ]])->assertOk()
            ->assertJsonPath('data.reply', "Ali Valiyevning qarzi 250 000 so'm. Shim kam qolgan.")
            ->assertJsonPath('data.tools_used', ['customer_debt', 'low_stock']);

        $requests = Http::recorded()->map(fn ($p) => $p[0]->data())->values()->all();
        $this->assertCount(2, $requests);

        // 1-so'rov: tizim ko'rsatmasi, vositalar ta'rifi, tarix va savol
        $this->assertStringContainsString('Vali Market', $requests[0]['system']);
        $this->assertSame(['customer_debt', 'low_stock'], array_values(array_intersect(['customer_debt', 'low_stock'], array_column($requests[0]['tools'], 'name'))));
        $this->assertCount(3, $requests[0]['messages']);

        // 2-so'rov: vosita natijalari qaytarilgan; faqat shu foydalanuvchi ma'lumoti
        $secondMessages = $requests[1]['messages'];
        $toolResults = end($secondMessages)['content'];
        $this->assertSame('tool_result', $toolResults[0]['type']);
        $this->assertSame('tu_1', $toolResults[0]['tool_use_id']);
        $this->assertStringContainsString('Ali Valiyev', $toolResults[0]['content']);
        $this->assertStringContainsString('250000', $toolResults[0]['content']);
        $this->assertStringNotContainsString('Begona', $toolResults[0]['content']);
        $this->assertStringContainsString('Shim', $toolResults[1]['content']);
    }

    public function test_sales_summary_tool_reads_real_numbers(): void
    {
        Sale::create([
            'user_id' => $this->user->id, 'status' => 'completed', 'payment_method' => 'cash',
            'subtotal' => 300000, 'discount' => 0, 'total' => 300000, 'paid_cash' => 300000,
            'total_cost' => 200000, 'profit' => 100000, 'sold_at' => now(),
        ]);

        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push(['stop_reason' => 'tool_use', 'content' => [['type' => 'tool_use', 'id' => 'a', 'name' => 'sales_summary', 'input' => (object) []]]])
            ->push(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'Bugun 300 000 so\'m savdo.']]]),
        ]);

        $this->ask('Bugungi savdo?')->assertOk()->assertJsonPath('data.tools_used', ['sales_summary']);

        $second = Http::recorded()->last()[0]->data();
        $result = json_decode(end($second['messages'])['content'][0]['content'], true);
        $this->assertSame(1, $result['sales_count']);
        $this->assertEquals(300000, $result['total_revenue']);
        $this->assertEquals(100000, $result['gross_profit']);
    }

    public function test_tool_loop_is_bounded_and_api_failure_is_reported(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'tool_use',
            'content' => [['type' => 'tool_use', 'id' => 'x', 'name' => 'overdue_debts', 'input' => (object) []]],
        ])]);
        $this->ask('cheksiz sikl')->assertStatus(502)->assertJsonPath('code', 'ai_failed');
        $this->assertCount(5, Http::recorded());

        Http::fake(['api.anthropic.com/*' => Http::response('xato', 500)]);
        $this->ask('salom')->assertStatus(502);
    }
}
