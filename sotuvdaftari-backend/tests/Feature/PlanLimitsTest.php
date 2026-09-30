<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function addCustomer(string $name = 'Mijoz')
    {
        return $this->postJson('/api/v1/customers', ['name' => $name]);
    }

    public function test_free_plan_customer_limit(): void
    {
        config()->set('savdodaftar.limits.free_customers', 2);
        Sanctum::actingAs(User::factory()->create());

        $this->addCustomer('A')->assertCreated();
        $this->addCustomer('B')->assertCreated();
        $this->addCustomer('C')->assertStatus(403)
            ->assertJsonPath('code', 'limit_reached')
            ->assertJsonPath('meta.resource', 'customers')
            ->assertJsonPath('meta.limit', 2)
            ->assertJsonPath('meta.required_plan', 'standard');
    }

    public function test_standard_limits_come_from_plans_table_and_admin_can_change_them(): void
    {
        $this->assertSame(300, Plan::where('key', 'standard')->value('max_customers'));
        $this->assertNull(Plan::where('key', 'pro')->value('max_customers'));

        Plan::where('key', 'standard')->update(['max_customers' => 1, 'max_products' => 1]);

        $user = User::factory()->standard()->create();
        Sanctum::actingAs($user);

        $this->addCustomer('A')->assertCreated();
        $this->addCustomer('B')->assertStatus(403)->assertJsonPath('meta.required_plan', 'pro');

        $this->postJson('/api/v1/products', ['name' => 'Futbolka', 'sell_price' => 100000])->assertCreated();
        $this->postJson('/api/v1/products', ['name' => 'Shim', 'sell_price' => 100000])
            ->assertStatus(403)->assertJsonPath('meta.resource', 'products');

        // Idempotent so'rov (client_uuid) limit tufayli xato bermaydi
        $uuid = (string) \Illuminate\Support\Str::uuid();
        Plan::where('key', 'standard')->update(['max_customers' => 5]);
        $first = $this->postJson('/api/v1/customers', ['name' => 'U', 'client_uuid' => $uuid])->assertCreated()->json('data.id');
        Plan::where('key', 'standard')->update(['max_customers' => 2]);
        $this->postJson('/api/v1/customers', ['name' => 'U', 'client_uuid' => $uuid])->assertSuccessful()->assertJsonPath('data.id', $first);

        // Admin paneldan limitni bo'sh qoldirish — cheksiz
        $admin = User::factory()->create(['phone' => '+998900000001']);
        $admin->forceFill(['is_admin' => true, 'admin_password' => 'secret-pass-1'])->save();
        $this->actingAs($admin->refresh())
            ->put('/admin/plans/standard', ['price' => 12000, 'is_active' => 1, 'max_customers' => '', 'max_products' => '7'])
            ->assertSessionHas('status');
        $this->assertNull(Plan::where('key', 'standard')->value('max_customers'));
        $this->assertSame(7, Plan::where('key', 'standard')->value('max_products'));

        Sanctum::actingAs($user);
        $this->addCustomer('Cheksiz')->assertCreated();
    }

    public function test_pro_is_unlimited_and_limits_are_exposed_in_plans(): void
    {
        config()->set('savdodaftar.limits.free_customers', 1);
        Plan::where('key', 'standard')->update(['max_customers' => 1]);

        $pro = User::factory()->pro()->create();
        Sanctum::actingAs($pro);
        foreach (range(1, 5) as $i) {
            $this->addCustomer("M{$i}")->assertCreated();
        }
        $this->assertSame(5, Customer::forUser($pro)->count());

        $this->getJson('/api/v1/billing/plan')
            ->assertJsonPath('data.plans.0.id', 'standard')
            ->assertJsonPath('data.plans.0.limits.customers', 1)
            ->assertJsonPath('data.plans.1.id', 'pro')
            ->assertJsonPath('data.plans.1.limits.customers', null);
    }

    public function test_pro_ocr_import_is_not_limited_by_standard_limits(): void
    {
        Plan::where('key', 'pro')->update(['max_customers' => null]);
        Plan::where('key', 'standard')->update(['max_customers' => 1]);

        // OCR — Pro; Pro cheksiz, shuning uchun Standart limit OCR'ga ta'sir qilmaydi
        $pro = User::factory()->pro()->create();
        Sanctum::actingAs($pro);
        $this->postJson('/api/v1/ai/ocr-import/confirm', ['items' => [
            ['name' => 'A', 'amount' => 1000], ['name' => 'B', 'amount' => 2000],
        ]])->assertCreated()->assertJsonPath('data.customers_created', 2);
    }
}
