<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Debt;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiVoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->pro()->create();
        Sanctum::actingAs($this->user);
    }

    private function say(string $text, array $headers = [])
    {
        return $this->postJson('/api/v1/ai/voice', ['text' => $text], $headers);
    }

    private function customer(string $name): Customer
    {
        return Customer::create(['user_id' => $this->user->id, 'name' => $name, 'phone' => '+99890'.random_int(1000000, 9999999), 'balance' => 0]);
    }

    public function test_voice_is_pro_only(): void
    {
        Sanctum::actingAs(User::factory()->standard()->create());
        $this->say('Ali akaga 150 ming qarz yoz')->assertStatus(403)
            ->assertJsonPath('code', 'plan_required')->assertJsonPath('meta.required_plan', 'pro');

        Sanctum::actingAs(User::factory()->create());
        $this->say('salom')->assertStatus(403);
    }

    public function test_debt_command_resolves_customer_and_asks_for_confirmation(): void
    {
        $ali = $this->customer('Ali Valiyev');
        $this->customer('Karim');

        $this->say('Ali akaga 150 ming qarz yoz.')->assertOk()
            ->assertJsonPath('data.intent', 'debt_add')
            ->assertJsonPath('data.needs_confirmation', true)
            ->assertJsonPath('data.params.customer.id', $ali->id)
            ->assertJsonPath('data.params.amount', 150000)
            ->assertJsonPath('data.params.missing', [])
            ->assertJsonPath('data.message', 'Ali Valiyev ga 150 000 so\'m qarz yozilsinmi?');

        // Hech narsa yozilmagan — faqat tahlil
        $this->assertSame(0, Debt::count());

        $this->say('Ali 100 ming qarzini berdi')->assertOk()
            ->assertJsonPath('data.intent', 'debt_payment')
            ->assertJsonPath('data.params.customer.id', $ali->id)
            ->assertJsonPath('data.params.amount', 100000);

        // Rus tilida, kirillcha ism lotincha mijozga moslashadi
        $this->say('Али вернул 100 тысяч', ['Accept-Language' => 'ru'])->assertOk()
            ->assertJsonPath('data.params.customer.id', $ali->id)
            ->assertJsonPath('data.params.amount', 100000)
            ->assertJsonPath('data.message', 'Ali Valiyev вернул 100 000 сум долга — принять?');
    }

    public function test_missing_or_unknown_customer_and_amount(): void
    {
        $this->customer('Ali');
        $this->customer('Alisher');

        // Noma'lum mijoz
        $this->say('Bobur akaga 50 ming qarz yoz')->assertOk()
            ->assertJsonPath('data.needs_confirmation', false)
            ->assertJsonPath('data.params.missing', ['customer'])
            ->assertJsonPath('data.params.candidates', []);

        // Summa aytilmagan
        $this->say('Ali akaga qarz yoz')->assertOk()
            ->assertJsonPath('data.needs_confirmation', false)
            ->assertJsonPath('data.params.missing', ['amount'])
            ->assertJsonPath('data.message', 'Summani aniq ayting. Masalan: "150 ming".');

        // Noaniq ism -> nomzodlar ro'yxati
        $this->say('Alis akaga 10 ming qarz yoz')->assertOk()
            ->assertJsonPath('data.needs_confirmation', false)
            ->assertJsonCount(2, 'data.params.candidates');
    }

    public function test_stock_in_command(): void
    {
        $shirt = Product::factory()->for($this->user)->create(['name' => 'Futbolka', 'unit' => 'dona', 'stock' => 5]);

        $this->say('Futbolka 10 dona kirim qil')->assertOk()
            ->assertJsonPath('data.intent', 'stock_in')
            ->assertJsonPath('data.needs_confirmation', true)
            ->assertJsonPath('data.params.product.id', $shirt->id)
            ->assertJsonPath('data.params.quantity', 10);

        $this->say('Shim besh dona kirim qil')->assertOk()
            ->assertJsonPath('data.needs_confirmation', false)
            ->assertJsonPath('data.params.missing', ['product']);

        $this->assertEquals(5.0, (float) $shirt->fresh()->stock); // faqat tahlil, bajarilmadi
    }

    public function test_read_commands_answer_immediately(): void
    {
        $this->say('Bugungi savdoni ko\'rsat')->assertOk()
            ->assertJsonPath('data.intent', 'show_sales')
            ->assertJsonPath('data.needs_confirmation', false)
            ->assertJsonPath('data.result.sales_count', 0);

        $this->say('Bugun qancha foyda qildim')->assertOk()
            ->assertJsonPath('data.intent', 'show_profit')
            ->assertJsonPath('data.result.net_profit', 0);

        $this->say('Kimlarning qarzi muddati o\'tgan')->assertOk()
            ->assertJsonPath('data.intent', 'show_debts')
            ->assertJsonPath('data.message', 'Muddati o\'tgan qarz yo\'q.');

        Product::factory()->for($this->user)->create(['name' => 'Shim', 'stock' => 1, 'min_stock' => 5]);
        $this->say('Kam qolgan mahsulotlarni ko\'rsat')->assertOk()
            ->assertJsonPath('data.intent', 'show_low_stock')
            ->assertJsonPath('data.result.count', 1)
            ->assertJsonPath('data.result.items.0.name', 'Shim');

        $this->say('salom qandaysiz')->assertOk()->assertJsonPath('data.intent', 'unknown');
        $this->say('')->assertStatus(422);
    }

    public function test_uzbek_in_cyrillic_and_latin_variants(): void
    {
        $ali = $this->customer('Ali');

        // Lotin: turli yozilishlar
        foreach (['Ali akaga 150 ming qarz yoz', 'ali aka 150000 som qarz yozib qoy', 'Aliga bir yuz ellik ming qarz yozing'] as $text) {
            $this->say($text)->assertOk()
                ->assertJsonPath('data.intent', 'debt_add')
                ->assertJsonPath('data.params.amount', 150000)
                ->assertJsonPath('data.params.customer.id', $ali->id);
        }

        // Ovoz tanish xizmati kirillda qaytarsa ham
        $this->say('Али акага 150 минг қарз ёз')->assertOk()
            ->assertJsonPath('data.intent', 'debt_add')
            ->assertJsonPath('data.params.amount', 150000)
            ->assertJsonPath('data.params.customer.id', $ali->id);

        $this->say('Али қарзини 50 минг тўлади')->assertOk()
            ->assertJsonPath('data.intent', 'debt_payment')
            ->assertJsonPath('data.params.amount', 50000);

        // Rus tili buzilmaydi
        $this->say('Запиши Али долг 150 тысяч')->assertOk()
            ->assertJsonPath('data.intent', 'debt_add')
            ->assertJsonPath('data.params.amount', 150000);
    }
}
