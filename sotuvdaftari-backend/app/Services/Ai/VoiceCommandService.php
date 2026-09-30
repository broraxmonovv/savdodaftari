<?php

namespace App\Services\Ai;

use App\Models\Customer;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Ovozli buyruqni bajarishga tayyorlaydi (Pro): matnni tahlil qiladi, mijoz/mahsulotni
 * bazadan topadi. Yozuvchi amallar (qarz, to'lov, kirim) bu yerda BAJARILMAYDI — ilova
 * foydalanuvchi tasdiqlagach mavjud API'lar orqali bajaradi (TZ 8, 34). O'qish so'rovlariga
 * javob darhol beriladi.
 */
class VoiceCommandService
{
    public function __construct(private readonly VoiceCommandParser $parser) {}

    /** @return array<string, mixed> */
    public function handle(User $user, string $text): array
    {
        $parsed = $this->parser->parse($text);
        $intent = $parsed['intent'];

        $base = [
            'intent' => $intent,
            'transcript' => $text,
            'needs_confirmation' => false,
            'message' => __('messages.ai.unknown'),
            'params' => (object) [],
            'result' => null,
        ];

        return match ($intent) {
            VoiceCommandParser::DEBT_ADD, VoiceCommandParser::DEBT_PAYMENT => $this->debt($user, $parsed, $base),
            VoiceCommandParser::STOCK_IN => $this->stockIn($user, $parsed, $base),
            VoiceCommandParser::SHOW_SALES, VoiceCommandParser::SHOW_PROFIT => $this->sales($user, $intent, $base),
            VoiceCommandParser::SHOW_DEBTS => $this->overdueDebts($user, $base),
            VoiceCommandParser::SHOW_LOW_STOCK => $this->lowStock($user, $base),
            default => $base,
        };
    }

    private function debt(User $user, array $parsed, array $base): array
    {
        $payment = $parsed['intent'] === VoiceCommandParser::DEBT_PAYMENT;
        $missing = [];

        if ($parsed['amount'] === null) {
            $missing[] = 'amount';
        }

        if ($parsed['name'] === null) {
            $missing[] = 'customer';
        }

        [$customer, $candidates] = $parsed['name'] === null
            ? [null, collect()]
            : $this->matchCustomer($user, $parsed['name']);

        if ($parsed['name'] !== null && $customer === null) {
            $missing[] = 'customer';
        }

        $params = [
            'customer' => $customer ? ['id' => $customer->id, 'name' => $customer->name, 'balance' => (float) $customer->balance] : null,
            'customer_name' => $parsed['name'],
            'candidates' => $candidates->map(fn (Customer $c) => ['id' => $c->id, 'name' => $c->name])->values()->all(),
            'amount' => $parsed['amount'],
            'missing' => array_values(array_unique($missing)),
        ];

        $ready = $params['missing'] === [];
        $key = $payment ? 'messages.ai.debt_payment' : 'messages.ai.debt_add';

        return array_merge($base, [
            'needs_confirmation' => $ready,
            'params' => $params,
            'message' => $ready
                ? __($key, ['name' => $customer->name, 'amount' => $this->money($parsed['amount'])])
                : $this->missingMessage($params, $parsed['name']),
        ]);
    }

    private function stockIn(User $user, array $parsed, array $base): array
    {
        $missing = [];
        $product = null;
        $candidates = collect();

        if ($parsed['quantity'] === null) {
            $missing[] = 'quantity';
        }

        if ($parsed['product'] === null) {
            $missing[] = 'product';
        } else {
            [$product, $candidates] = $this->matchProduct($user, $parsed['product']);

            if ($product === null) {
                $missing[] = 'product';
            }
        }

        $params = [
            'product' => $product ? ['id' => $product->id, 'name' => $product->name, 'unit' => $product->unit, 'stock' => (float) $product->stock] : null,
            'product_name' => $parsed['product'],
            'candidates' => $candidates->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
            'quantity' => $parsed['quantity'],
            'missing' => $missing,
        ];

        $ready = $missing === [];

        return array_merge($base, [
            'needs_confirmation' => $ready,
            'params' => $params,
            'message' => $ready
                ? __('messages.ai.stock_in', ['product' => $product->name, 'qty' => $this->qty($parsed['quantity']), 'unit' => $product->unit])
                : __('messages.ai.product_not_found', ['name' => $parsed['product'] ?? '—']),
        ]);
    }

    private function sales(User $user, string $intent, array $base): array
    {
        $today = today()->toDateString();

        $row = Sale::forUser($user)->between($today, $today)
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(total - returned_total), 0) as total,'
                .' COALESCE(SUM(COALESCE(profit, 0) - (returned_total - returned_cost)), 0) as profit')
            ->first();

        $expenses = (float) Expense::forUser($user)->whereDate('spent_at', $today)->sum('amount');
        $profit = (float) $row->profit;
        $net = round($profit - $expenses, 2);

        $result = [
            'sales_count' => (int) $row->c,
            'sales_total' => (float) $row->total,
            'gross_profit' => $profit,
            'expenses' => $expenses,
            'net_profit' => $net,
        ];

        return array_merge($base, [
            'result' => $result,
            'message' => $intent === VoiceCommandParser::SHOW_PROFIT
                ? __('messages.ai.profit', ['profit' => $this->money($profit), 'expenses' => $this->money($expenses), 'net' => $this->money($net)])
                : __('messages.ai.sales', ['count' => $result['sales_count'], 'total' => $this->money($result['sales_total']), 'profit' => $this->money($profit)]),
        ]);
    }

    private function overdueDebts(User $user, array $base): array
    {
        $debts = Debt::forUser($user)->overdue()->with('customer')->orderBy('due_date')->limit(10)->get();
        $total = $debts->sum(fn (Debt $d) => (float) $d->remaining);

        return array_merge($base, [
            'result' => [
                'count' => $debts->count(),
                'total' => round($total, 2),
                'items' => $debts->map(fn (Debt $d) => [
                    'name' => $d->customer?->name,
                    'amount' => (float) $d->remaining,
                    'due_date' => $d->due_date?->toDateString(),
                ])->values()->all(),
            ],
            'message' => $debts->isEmpty()
                ? __('messages.ai.no_overdue')
                : __('messages.ai.overdue', ['count' => $debts->count(), 'total' => $this->money($total)]),
        ]);
    }

    private function lowStock(User $user, array $base): array
    {
        $products = Product::forUser($user)->active()->needsAttention()->orderBy('stock')->limit(10)->get();

        return array_merge($base, [
            'result' => [
                'count' => $products->count(),
                'items' => $products->map(fn (Product $p) => [
                    'name' => $p->name,
                    'stock' => (float) $p->stock,
                    'min_stock' => (float) $p->min_stock,
                    'unit' => $p->unit,
                ])->values()->all(),
            ],
            'message' => $products->isEmpty()
                ? __('messages.ai.no_low_stock')
                : __('messages.ai.low_stock', ['count' => $products->count()]),
        ]);
    }

    /** @return array{0: ?Customer, 1: Collection<int, Customer>} */
    private function matchCustomer(User $user, string $name): array
    {
        return $this->match(Customer::forUser($user)->get(), $name);
    }

    /** @return array{0: ?Product, 1: Collection<int, Product>} */
    private function matchProduct(User $user, string $name): array
    {
        return $this->match(Product::forUser($user)->active()->get(), $name);
    }

    /**
     * Nom bo'yicha eng yaqin yozuv: aniq moslik (>=75%) yagona bo'lsa — o'sha, aks holda
     * (bir nechta yoki past) — nomzodlar ro'yxati (>=50%).
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, T>  $items
     * @return array{0: ?T, 1: Collection<int, T>}
     */
    private function match(Collection $items, string $spoken): array
    {
        $needle = $this->parser->transliterate($spoken);

        $scored = $items->map(function ($item) use ($needle) {
            $hay = $this->parser->transliterate((string) $item->name);
            similar_text($needle, $hay, $percent);

            // Gapirilgan so'z nomning boshlanishi bo'lsa ("ali" -> "ali valiyev") — yuqori ball
            if ($needle !== '' && str_starts_with($hay, $needle)) {
                $percent = max($percent, 90);
            }

            return ['item' => $item, 'score' => $percent];
        })->sortByDesc('score')->values();

        $strong = $scored->filter(fn ($r) => $r['score'] >= 75);

        if ($strong->count() === 1 || ($strong->count() > 1 && $strong[0]['score'] - $strong[1]['score'] >= 8)) {
            return [$strong->first()['item'], collect()];
        }

        $candidates = $scored->filter(fn ($r) => $r['score'] >= 50)->take(3)->pluck('item');

        return [null, $candidates];
    }

    private function missingMessage(array $params, ?string $spoken): string
    {
        if (in_array('customer', $params['missing'], true)) {
            return $params['candidates'] !== []
                ? __('messages.ai.choose_customer', ['name' => $spoken])
                : __('messages.ai.customer_not_found', ['name' => $spoken ?? '—']);
        }

        return __('messages.ai.need_amount');
    }

    private function money(float $amount): string
    {
        return number_format($amount, 0, '.', ' ').' '.__('messages.ai.currency');
    }

    private function qty(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
    }
}
