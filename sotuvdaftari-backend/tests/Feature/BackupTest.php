<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('savdodaftar.backup.disk', 'local');

        $this->user = User::factory()->pro()->create(['shop_name' => 'Bozor Market']);
        Sanctum::actingAs($this->user);
    }

    private function seedData(): void
    {
        $product = Product::factory()->for($this->user)->create([
            'name' => 'Futbolka',
            'buy_price' => 70000,
            'sell_price' => 120000,
            'stock' => 50,
            'min_stock' => 5,
        ]);

        Customer::factory()->for($this->user)->create(['name' => 'Ali aka']);

        $this->postJson('/api/v1/sales', [
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'qty' => 2]],
        ])->assertCreated();

        $this->postJson('/api/v1/expenses', ['category' => 'transport', 'amount' => 30000])->assertCreated();
    }

    public function test_create_list_download_and_delete_backup(): void
    {
        $this->seedData();

        $backup = $this->postJson('/api/v1/backups')
            ->assertCreated()
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.counts.customers', 1)
            ->assertJsonPath('data.counts.products', 1)
            ->assertJsonPath('data.counts.sales', 1)
            ->assertJsonPath('data.counts.expenses', 1)
            ->json('data');

        $this->assertGreaterThan(0, $backup['size']);
        $this->assertNotEmpty($backup['checksum']);

        Storage::disk('local')->assertExists(Backup::findOrFail($backup['id'])->path);

        $this->getJson('/api/v1/backups')
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.items.0.id', $backup['id']);

        // Tiklash uchun payload
        $this->getJson("/api/v1/backups/{$backup['id']}")
            ->assertOk()
            ->assertJsonPath('data.payload.version', 1)
            ->assertJsonPath('data.payload.user.shop_name', 'Bozor Market')
            ->assertJsonPath('data.payload.data.products.0.name', 'Futbolka')
            ->assertJsonPath('data.payload.data.customers.0.name', 'Ali aka')
            ->assertJsonPath('data.payload.counts.sales', 1)
            ->assertJsonPath('data.payload.data.sales.0.items.0.name', 'Futbolka');

        $path = Backup::findOrFail($backup['id'])->path;
        $this->deleteJson("/api/v1/backups/{$backup['id']}")->assertOk();
        Storage::disk('local')->assertMissing($path);
        $this->getJson('/api/v1/backups')->assertJsonPath('data.count', 0);
    }

    public function test_retention_keeps_only_last_n_backups(): void
    {
        config()->set('savdodaftar.backup.keep', 2);
        $this->seedData();

        $first = $this->postJson('/api/v1/backups')->assertCreated()->json('data.id');
        $firstPath = Backup::findOrFail($first)->path;

        $this->postJson('/api/v1/backups')->assertCreated();
        $this->postJson('/api/v1/backups')->assertCreated();

        $this->getJson('/api/v1/backups')->assertJsonPath('data.count', 2);
        $this->assertNull(Backup::find($first));
        Storage::disk('local')->assertMissing($firstPath);
    }

    public function test_backups_are_scoped_to_user(): void
    {
        $this->seedData();
        $id = $this->postJson('/api/v1/backups')->assertCreated()->json('data.id');

        Sanctum::actingAs(User::factory()->pro()->create());

        $this->getJson('/api/v1/backups')->assertOk()->assertJsonPath('data.count', 0);
        $this->getJson("/api/v1/backups/{$id}")->assertNotFound();
        $this->deleteJson("/api/v1/backups/{$id}")->assertNotFound();

        $this->assertNotNull(Backup::find($id));
    }

    public function test_restore_returns_data_to_backup_state_and_keeps_other_users_intact(): void
    {
        $this->seedData();

        $other = User::factory()->pro()->create();
        Customer::factory()->for($other)->create(['name' => 'Boshqa mijoz']);

        $ids = [
            'customers' => \DB::table('customers')->where('user_id', $this->user->id)->pluck('id')->all(),
            'products' => \DB::table('products')->where('user_id', $this->user->id)->pluck('id')->all(),
            'sales' => \DB::table('sales')->where('user_id', $this->user->id)->pluck('id')->all(),
            'sale_items' => \DB::table('sale_items')->where('user_id', $this->user->id)->pluck('id')->all(),
            'stock_movements' => \DB::table('stock_movements')->where('user_id', $this->user->id)->pluck('id')->all(),
            'expenses' => \DB::table('expenses')->where('user_id', $this->user->id)->pluck('id')->all(),
        ];
        $stock = (float) \DB::table('products')->where('user_id', $this->user->id)->value('stock');
        $saleTotal = (float) \DB::table('sales')->where('user_id', $this->user->id)->value('total');

        $backupId = $this->postJson('/api/v1/backups')->assertCreated()->json('data.id');

        // Zaxiradan keyin ma'lumotlar buziladi: mijoz o'chadi, yangi mahsulot/xarajat qo'shiladi, qoldiq o'zgaradi
        \DB::table('customers')->where('user_id', $this->user->id)->delete();
        \DB::table('products')->where('user_id', $this->user->id)->update(['stock' => 1]);
        Product::factory()->for($this->user)->create(['name' => 'Keyin qo\'shilgan']);
        $this->postJson('/api/v1/expenses', ['category' => 'rent', 'amount' => 10])->assertCreated();

        // Tasdiqsiz tiklab bo'lmaydi
        $this->postJson("/api/v1/backups/{$backupId}/restore")->assertStatus(422);

        $response = $this->postJson("/api/v1/backups/{$backupId}/restore", ['confirm' => true])->assertOk()
            ->assertJsonPath('data.restored.customers', 1)
            ->assertJsonPath('data.restored.sales', 1);
        unset($response);

        foreach ($ids as $table => $expected) {
            $this->assertEqualsCanonicalizing(
                $expected,
                \DB::table($table)->where('user_id', $this->user->id)->pluck('id')->all(),
                "{$table}: asl ID'lar tiklanishi kerak",
            );
        }

        $this->assertSame('Ali aka', \DB::table('customers')->where('user_id', $this->user->id)->value('name'));
        $this->assertSame($stock, (float) \DB::table('products')->where('user_id', $this->user->id)->value('stock'));
        $this->assertSame($saleTotal, (float) \DB::table('sales')->where('user_id', $this->user->id)->value('total'));
        $this->assertSame(0, \DB::table('products')->where('name', 'Keyin qo\'shilgan')->count());

        // Boshqa foydalanuvchi ma'lumotlariga tegilmagan
        $this->assertSame(1, \DB::table('customers')->where('user_id', $other->id)->count());

        // Tiklashdan oldingi holat avtomatik zaxira sifatida saqlangan (qaytish nuqtasi)
        $this->assertSame(1, Backup::forUser($this->user)->where('source', 'auto')->count());

        // Tiklangan ma'lumotlar bilan ilova qayta ishlaydi
        $this->getJson('/api/v1/sales')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/customers')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_restore_rejects_corrupted_backup_and_other_users_backup(): void
    {
        $this->seedData();
        $backup = Backup::findOrFail($this->postJson('/api/v1/backups')->json('data.id'));

        // Fayl buzilgan (checksum mos emas) — hech narsa o'zgarmaydi
        Storage::disk('local')->put($backup->path, '{"version":1,"user":{"id":'.$this->user->id.'},"data":{}}');
        $this->postJson("/api/v1/backups/{$backup->id}/restore", ['confirm' => true])
            ->assertStatus(422)->assertJsonPath('code', 'backup_corrupted');
        $this->assertSame(1, \DB::table('customers')->where('user_id', $this->user->id)->count());

        // Boshqa foydalanuvchining zaxirasi ko'rinmaydi
        Sanctum::actingAs(User::factory()->pro()->create());
        $this->postJson("/api/v1/backups/{$backup->id}/restore", ['confirm' => true])->assertStatus(404);
    }
}
