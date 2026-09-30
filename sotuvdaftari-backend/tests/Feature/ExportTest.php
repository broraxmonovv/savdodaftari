<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->pro()->create(['shop_name' => "Vali O'tkir Market"]);
        Sanctum::actingAs($this->user);

        $customer = Customer::create(['user_id' => $this->user->id, 'name' => 'Ali Valiyev', 'phone' => '+998901112233', 'balance' => 50000]);
        Sale::create([
            'user_id' => $this->user->id, 'customer_id' => $customer->id, 'status' => 'completed', 'payment_method' => 'cash',
            'subtotal' => 300000, 'discount' => 0, 'total' => 300000, 'paid_cash' => 300000,
            'total_cost' => 200000, 'profit' => 100000, 'sold_at' => now(),
        ]);
        Expense::create(['user_id' => $this->user->id, 'category' => 'rent', 'amount' => 40000, 'note' => 'Ijara', 'spent_at' => today()]);
        Debt::create(['user_id' => $this->user->id, 'customer_id' => $customer->id, 'amount' => 50000, 'paid_amount' => 0, 'status' => 'open', 'due_date' => today()->addDays(5), 'issued_at' => now()]);
        Product::factory()->for($this->user)->create(['name' => "O'rikzor futbolka", 'stock' => 7, 'buy_price' => 70000, 'sell_price' => 120000]);
    }

    /** Javob kontentini vaqtinchalik faylga yozib, .xlsx (zip) ichidagi barcha XML matnini qaytaradi */
    private function xlsxText($response): string
    {
        $path = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($path, $response->baseResponse->getFile()->getContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'xlsx zip arxiv bo\'lishi kerak');

        $text = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, 'xl/') && str_ends_with($name, '.xml')) {
                $text .= $zip->getFromIndex($i);
            }
        }
        $zip->close();
        unlink($path);

        return $text;
    }

    public function test_exports_are_pro_only_and_validated(): void
    {
        Sanctum::actingAs(User::factory()->standard()->create());
        $this->get('/api/v1/exports/sales?format=xlsx')->assertStatus(403)->assertJsonPath('code', 'plan_required');

        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/exports/nope?format=xlsx')->assertStatus(404);
        $this->getJson('/api/v1/exports/sales')->assertStatus(422);
        $this->getJson('/api/v1/exports/sales?format=doc')->assertStatus(422);
        $this->getJson('/api/v1/exports/sales?format=pdf&from=2026-02-01&to=2026-01-01')->assertStatus(422);
    }

    public function test_excel_exports_contain_real_data(): void
    {
        $sales = $this->get('/api/v1/exports/sales?format=xlsx')->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $sales->headers->get('Content-Type'));
        $this->assertStringContainsString('savdoup-sales-', $sales->headers->get('Content-Disposition'));
        $text = $this->xlsxText($sales);
        $this->assertStringContainsString('Ali Valiyev', $text);
        $this->assertStringContainsString('300000', $text);
        $this->assertStringContainsString("O'tkir Market", str_replace(['&apos;', '&#039;'], "'", $text));

        $report = $this->xlsxText($this->get('/api/v1/exports/report?format=xlsx')->assertOk());
        $this->assertStringContainsString('100000', $report);      // yalpi foyda
        $this->assertStringContainsString('60000', $report);       // sof foyda = 100000 - 40000

        $this->assertStringContainsString('Ijara', $this->xlsxText($this->get('/api/v1/exports/expenses?format=xlsx')->assertOk()));
        $this->assertStringContainsString('50000', $this->xlsxText($this->get('/api/v1/exports/debts?format=xlsx')->assertOk()));
        $inv = $this->xlsxText($this->get('/api/v1/exports/inventory?format=xlsx')->assertOk());
        $this->assertStringContainsString('futbolka', $inv);
        $this->assertStringContainsString('490000', $inv);         // 7 * 70000
    }

    public function test_pdf_exports_are_valid_pdfs_and_follow_language(): void
    {
        foreach (['report', 'sales', 'expenses', 'debts', 'inventory'] as $type) {
            $response = $this->get("/api/v1/exports/{$type}?format=pdf")->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringStartsWith('%PDF', $response->getContent(), $type);
        }

        // Rus tili va kirill harflar PDF'ni buzmaydi
        $ru = $this->get('/api/v1/exports/report?format=pdf', ['Accept-Language' => 'ru'])->assertOk();
        $this->assertStringStartsWith('%PDF', $ru->getContent());

        // Bo'sh davr ham xatosiz
        $this->get('/api/v1/exports/sales?format=pdf&from=2020-01-01&to=2020-01-02')->assertOk();
        $this->get('/api/v1/exports/sales?format=xlsx&from=2020-01-01&to=2020-01-02')->assertOk();
    }
}
