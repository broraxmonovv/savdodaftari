<?php

namespace App\Services\Backup;

use App\Exceptions\ApiException;
use App\Models\Backup;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * TZ 2, 23: bulutga zaxira nusxa va tiklash.
 *
 * Zaxira — foydalanuvchining barcha asosiy ma'lumotlarining JSON snapshot'i.
 * Mobil ilova payload'ni yuklab olib, lokal bazani tiklaydi (offline-first).
 */
class BackupService
{
    public const VERSION = 1;

    public function create(User $user, string $source = Backup::SOURCE_MANUAL): Backup
    {
        $data = [
            'customers' => Customer::forUser($user)->get()->toArray(),
            'debts' => Debt::forUser($user)->get()->toArray(),
            'debt_payments' => DebtPayment::forUser($user)->get()->toArray(),
            'products' => Product::forUser($user)->get()->toArray(),
            'stock_movements' => StockMovement::forUser($user)->get()->toArray(),
            'sales' => Sale::forUser($user)->with('items')->get()->toArray(),
            'sale_returns' => SaleReturn::forUser($user)->with('items')->get()->toArray(),
            'expenses' => Expense::forUser($user)->get()->toArray(),
        ];

        $counts = collect($data)->map(fn (array $rows) => count($rows))->all();

        $payload = [
            'version' => self::VERSION,
            'created_at' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'shop_name' => $user->shop_name,
            ],
            'counts' => $counts,
            'data' => $data,
        ];

        $json = (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
        $path = sprintf('backups/%d/%s_%s.json', $user->id, now()->format('Ymd_His'), Str::lower(Str::random(8)));

        Storage::disk($this->disk())->put($path, $json);

        $backup = Backup::create([
            'user_id' => $user->id,
            'path' => $path,
            'size' => strlen($json),
            'checksum' => hash('sha256', $json),
            'counts' => $counts,
            'source' => $source,
        ]);

        $this->prune($user);

        return $backup;
    }

    /** Tiklash uchun zaxira faylining to'liq payload'i */
    public function payload(Backup $backup): array
    {
        $storage = Storage::disk($this->disk());

        if (! $storage->exists($backup->path)) {
            throw new ApiException(__('messages.backup.file_missing'), 410, 'backup_file_missing');
        }

        return (array) json_decode((string) $storage->get($backup->path), true);
    }

    /**
     * Zaxiradan tiklash: foydalanuvchining joriy ma'lumotlari zaxiradagi holatga qaytariladi
     * (asl ID'lar saqlanadi). Xavfsizlik uchun avval joriy holatning avtomatik zaxirasi olinadi;
     * almashtirish bitta tranzaksiyada — xatoda hech narsa o'zgarmaydi.
     *
     * @return array<string, int> tiklangan yozuvlar soni (jadval bo'yicha)
     */
    public function restore(User $user, Backup $backup): array
    {
        $storage = Storage::disk($this->disk());

        if (! $storage->exists($backup->path)) {
            throw new ApiException(__('messages.backup.file_missing'), 410, 'backup_file_missing');
        }

        $json = (string) $storage->get($backup->path);

        if (! hash_equals((string) $backup->checksum, hash('sha256', $json))) {
            throw new ApiException(__('messages.backup.corrupted'), 422, 'backup_corrupted');
        }

        $payload = (array) json_decode($json, true);

        if (($payload['version'] ?? null) !== self::VERSION || ! is_array($payload['data'] ?? null)
            || (int) ($payload['user']['id'] ?? 0) !== $user->id) {
            throw new ApiException(__('messages.backup.invalid'), 422, 'backup_invalid');
        }

        // Tiklashdan oldingi holat — qaytish nuqtasi
        $this->create($user, Backup::SOURCE_AUTO);

        $data = $payload['data'];
        $counts = [];

        DB::transaction(function () use ($user, $data, &$counts) {
            $this->wipe($user);

            $sales = $data['sales'] ?? [];
            $returns = $data['sale_returns'] ?? [];

            $plan = [
                'customers' => $data['customers'] ?? [],
                'products' => $data['products'] ?? [],
                'sales' => $sales,
                'sale_items' => collect($sales)->flatMap(fn ($s) => $s['items'] ?? [])->all(),
                'sale_returns' => $returns,
                'sale_return_items' => collect($returns)->flatMap(fn ($r) => $r['items'] ?? [])->all(),
                'debts' => $data['debts'] ?? [],
                'debt_payments' => $data['debt_payments'] ?? [],
                'stock_movements' => $data['stock_movements'] ?? [],
                'expenses' => $data['expenses'] ?? [],
            ];

            foreach ($plan as $table => $rows) {
                $columns = Schema::getColumnListing($table);

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table($table)->insert(array_map(fn (array $row) => $this->prepareRow($row, $columns), $chunk));
                }

                $counts[$table] = count($rows);
            }
        });

        return $counts;
    }

    /** Foydalanuvchining barcha biznes ma'lumotlarini (soft-delete qilinganlarini ham) o'chiradi; bolalar avval */
    private function wipe(User $user): void
    {
        $returnIds = DB::table('sale_returns')->where('user_id', $user->id)->pluck('id');
        DB::table('sale_return_items')->whereIn('sale_return_id', $returnIds)->delete();
        DB::table('sale_returns')->where('user_id', $user->id)->delete();

        foreach (['debt_payments', 'debts', 'sale_items', 'stock_movements', 'sales', 'expenses', 'products', 'customers'] as $table) {
            DB::table($table)->where('user_id', $user->id)->delete();
        }
    }

    /**
     * JSON payload qatorini bazaga yozishga tayyorlaydi: faqat mavjud ustunlar,
     * ISO sanalar `Y-m-d H:i:s` ga, massivlar JSON'ga, bool — 0/1.
     *
     * @param  list<string>  $columns
     */
    private function prepareRow(array $row, array $columns): array
    {
        $out = [];

        foreach ($row as $key => $value) {
            if (! in_array($key, $columns, true)) {
                continue;
            }

            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value)) {
                $value = Carbon::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
            } elseif (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($value)) {
                $value = (int) $value;
            }

            $out[$key] = $value;
        }

        return $out;
    }

    public function delete(Backup $backup): void
    {
        Storage::disk($this->disk())->delete($backup->path);
        $backup->delete();
    }

    /** Har bir foydalanuvchi uchun faqat oxirgi N ta zaxira saqlanadi */
    private function prune(User $user): void
    {
        Backup::forUser($user)
            ->orderByDesc('id')
            ->skip($this->keep())
            ->take(100)
            ->get()
            ->each(fn (Backup $old) => $this->delete($old));
    }

    private function disk(): string
    {
        return (string) config('savdodaftar.backup.disk');
    }

    private function keep(): int
    {
        return max(1, (int) config('savdodaftar.backup.keep'));
    }
}
