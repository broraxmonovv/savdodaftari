<?php

namespace App\Services\Ai;

use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\User;
use App\Services\Customers\CustomerService;
use App\Services\Debts\DebtService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Eski qog'oz daftar rasmidan mijoz va qarz summalarini ajratadi (TZ 15, Pro).
 * [extract] hech narsa yozmaydi — natija foydalanuvchiga tekshirish uchun qaytadi;
 * faqat [import] (foydalanuvchi tasdiqlagach) mijoz va qarzlarni yaratadi.
 */
class OcrImportService
{
    private const PROMPT = <<<'TXT'
Bu rasmda qo'lda yozilgan (yoki bosma) qarz daftari sahifasi bor (o'zbek yoki rus tilida).
Har bir qatordan MIJOZ ISMI va QARZ SUMMASINI (so'mda, butun son) ajrat.
Faqat quyidagi JSON ni qaytar, boshqa matn yozma:
{"items":[{"name":"Ali","amount":150000,"phone":null,"note":null,"uncertain":false}]}
Qoidalar: summa so'mda butun son bo'lsin ("150 ming" = 150000, "1.5 mln" = 1500000); telefon raqam yozilgan bo'lsa phone ga yoz, aks holda null;
bo'sh yoki o'qib bo'lmaydigan qatorni o'tkazib yubor; ism yoki summa aniq o'qilmasa uncertain:true qo'y; hech narsa topilmasa {"items":[]}.
TXT;

    public function __construct(
        private readonly ClaudeClient $claude,
        private readonly CustomerService $customers,
        private readonly DebtService $debts,
        private readonly VoiceCommandParser $parser,
    ) {}

    /** @return list<array<string, mixed>> */
    public function extract(User $user, UploadedFile $image): array
    {
        $response = $this->claude->message([[
            'role' => 'user',
            'content' => [
                ['type' => 'image', 'source' => [
                    'type' => 'base64',
                    'media_type' => $this->mediaType($image),
                    'data' => base64_encode((string) file_get_contents($image->getRealPath())),
                ]],
                ['type' => 'text', 'text' => self::PROMPT],
            ],
        ]], maxTokens: 4000);

        $json = $this->decodeJson($this->claude->text($response));

        $items = [];

        foreach (array_slice((array) ($json['items'] ?? []), 0, 200) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $amount = $this->amount($row['amount'] ?? null);

            if ($name === '' || $amount === null) {
                continue;
            }

            $phone = $this->phone($row['phone'] ?? null);
            $existing = $this->findCustomer($user, $name, $phone);

            $items[] = [
                'name' => mb_substr($name, 0, 100),
                'amount' => $amount,
                'phone' => $phone,
                'note' => filled($row['note'] ?? null) ? mb_substr((string) $row['note'], 0, 200) : null,
                'uncertain' => (bool) ($row['uncertain'] ?? false),
                'existing_customer' => $existing ? ['id' => $existing->id, 'name' => $existing->name] : null,
            ];
        }

        return $items;
    }

    /**
     * Tasdiqlangan qatorlarni yozadi: mavjud mijozga qarz qo'shiladi, yo'q bo'lsa mijoz yaratiladi.
     * Hammasi bitta tranzaksiyada (yoki hammasi, yoki hech biri).
     *
     * @param  list<array{name: string, amount: float|int|string, phone?: ?string, note?: ?string}>  $items
     * @return array{customers_created: int, debts_created: int, total: float}
     */
    public function import(User $user, array $items): array
    {
        if ($items === []) {
            throw new ApiException(__('messages.ai.nothing_to_import'), 422, 'nothing_to_import');
        }

        return DB::transaction(function () use ($user, $items) {
            $created = 0;
            $total = 0.0;
            /** @var array<string, Customer> $cache Bir import ichida bir xil mijoz ikki marta yaratilmasin */
            $cache = [];

            foreach ($items as $item) {
                $phone = $this->phone($item['phone'] ?? null);
                $key = $phone ?? mb_strtolower(trim($item['name']));

                $customer = $cache[$key] ?? $this->findCustomer($user, $item['name'], $phone);

                if ($customer === null) {
                    $customer = $this->customers->create($user, array_filter([
                        'name' => trim($item['name']),
                        'phone' => $phone,
                        'note' => __('messages.ai.ocr_customer_note'),
                    ]));
                    $created++;
                }

                $cache[$key] = $customer;

                $this->debts->create($user, $customer, [
                    'amount' => (float) $item['amount'],
                    'note' => $item['note'] ?? __('messages.ai.ocr_debt_note'),
                ]);

                $total += (float) $item['amount'];
            }

            return [
                'customers_created' => $created,
                'debts_created' => count($items),
                'total' => round($total, 2),
            ];
        });
    }

    private function findCustomer(User $user, string $name, ?string $phone): ?Customer
    {
        if ($phone !== null) {
            $byPhone = Customer::forUser($user)->where('phone', $phone)->first();

            if ($byPhone !== null) {
                return $byPhone;
            }
        }

        $needle = $this->parser->transliterate(trim($name));

        return Customer::forUser($user)->get()
            ->first(fn (Customer $c) => $this->parser->transliterate(trim($c->name)) === $needle);
    }

    private function amount(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = preg_replace('/[^\d.]/', '', str_replace(',', '.', $value));
        }

        return is_numeric($value) && (float) $value > 0 && (float) $value < 1e12 ? round((float) $value, 2) : null;
    }

    private function phone(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (strlen($digits) === 9) {
            $digits = '998'.$digits;
        }

        return preg_match('/^998\d{9}$/', $digits) ? '+'.$digits : null;
    }

    private function mediaType(UploadedFile $image): string
    {
        $mime = (string) ($image->getMimeType() ?: 'image/jpeg');

        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) ? $mime : 'image/jpeg';
    }

    /** Model javobidan JSON ni ajratadi (```json ... ``` o'rovini ham hisobga oladi) */
    private function decodeJson(string $text): array
    {
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $decoded = json_decode($m[0], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new ApiException(__('messages.ai.failed'), 502, 'ai_failed');
    }
}
