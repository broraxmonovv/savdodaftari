<?php

namespace App\Services\Ai;

use App\Models\Customer;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;

/**
 * AI yordamchi uchun FAQAT O'QISH vositalari: har biri joriy foydalanuvchining ma'lumotlarini
 * qaytaradi (boshqa foydalanuvchilarga tegmaydi) va hech narsani o'zgartirmaydi.
 */
class AssistantTools
{
    /** @return list<array<string, mixed>> Claude `tools` ta'rifi */
    public function definitions(): array
    {
        $range = [
            'from' => ['type' => 'string', 'description' => 'Boshlanish sanasi Y-m-d (bo\'sh bo\'lsa — bugun)'],
            'to' => ['type' => 'string', 'description' => 'Tugash sanasi Y-m-d (bo\'sh bo\'lsa — from bilan bir xil)'],
        ];

        return [
            [
                'name' => 'sales_summary',
                'description' => 'Davr uchun savdo: savdolar soni, jami tushum, yalpi foyda, naqd/karta/qarz, qaytarishlar va xarajatlar, sof foyda.',
                'input_schema' => ['type' => 'object', 'properties' => $range],
            ],
            [
                'name' => 'top_products',
                'description' => 'Davr uchun eng ko\'p sotilgan yoki eng foydali mahsulotlar.',
                'input_schema' => ['type' => 'object', 'properties' => $range + [
                    'by' => ['type' => 'string', 'enum' => ['revenue', 'qty', 'profit']],
                    'limit' => ['type' => 'integer', 'description' => '1..20, standart 5'],
                ]],
            ],
            [
                'name' => 'overdue_debts',
                'description' => 'Muddati o\'tgan qarzlar ro\'yxati va jami summasi (kim, qancha, qachon).',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'low_stock',
                'description' => 'Minimal qoldiqdan past yoki tugagan mahsulotlar. `threshold` berilsa — qoldig\'i shu sondan kam mahsulotlar.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'threshold' => ['type' => 'number'],
                ]],
            ],
            [
                'name' => 'customer_debt',
                'description' => 'Mijoz ismi bo\'yicha qarz balansi (qisman moslik ham topiladi).',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'name' => ['type' => 'string'],
                ], 'required' => ['name']],
            ],
            [
                'name' => 'expenses_summary',
                'description' => 'Davr uchun xarajatlar: jami va kategoriyalar bo\'yicha.',
                'input_schema' => ['type' => 'object', 'properties' => $range],
            ],
        ];
    }

    /** @param  array<string, mixed>  $input */
    public function run(User $user, string $name, array $input): array
    {
        return match ($name) {
            'sales_summary' => $this->salesSummary($user, $input),
            'top_products' => $this->topProducts($user, $input),
            'overdue_debts' => $this->overdueDebts($user),
            'low_stock' => $this->lowStock($user, $input),
            'customer_debt' => $this->customerDebt($user, $input),
            'expenses_summary' => $this->expensesSummary($user, $input),
            default => ['error' => "Noma'lum vosita: {$name}"],
        };
    }

    /** @return array{0: string, 1: string} */
    private function range(array $input): array
    {
        $from = $this->date($input['from'] ?? null) ?? today()->toDateString();
        $to = $this->date($input['to'] ?? null) ?? $from;

        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function salesSummary(User $user, array $input): array
    {
        [$from, $to] = $this->range($input);

        $row = Sale::forUser($user)->between($from, $to)->selectRaw(
            'COUNT(*) as c, COALESCE(SUM(total), 0) as total, COALESCE(SUM(returned_total), 0) as returned,'
            .' COALESCE(SUM(COALESCE(profit, 0) - (returned_total - returned_cost)), 0) as profit,'
            .' COALESCE(SUM(paid_cash), 0) as cash, COALESCE(SUM(paid_card), 0) as card, COALESCE(SUM(debt_amount), 0) as debt'
        )->first();

        $expenses = (float) Expense::forUser($user)->between($from, $to)->sum('amount');
        $profit = round((float) $row->profit, 2);

        return [
            'from' => $from, 'to' => $to,
            'sales_count' => (int) $row->c,
            'total_revenue' => round((float) $row->total - (float) $row->returned, 2),
            'gross_profit' => $profit,
            'cash' => (float) $row->cash, 'card' => (float) $row->card, 'on_debt' => (float) $row->debt,
            'expenses' => $expenses,
            'net_profit' => round($profit - $expenses, 2),
            'currency' => "so'm",
        ];
    }

    private function topProducts(User $user, array $input): array
    {
        [$from, $to] = $this->range($input);
        $by = in_array($input['by'] ?? null, ['revenue', 'qty', 'profit'], true) ? $input['by'] : 'revenue';
        $limit = max(1, min(20, (int) ($input['limit'] ?? 5)));

        $rows = SaleItem::query()
            ->where('sale_items.user_id', $user->id)
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereNull('sales.deleted_at')
            ->where('sales.sold_at', '>=', $from.' 00:00:00')
            ->where('sales.sold_at', '<=', $to.' 23:59:59')
            ->selectRaw('sale_items.name as product_name')
            ->selectRaw('COALESCE(SUM(sale_items.qty - sale_items.returned_qty), 0) as qty_sum')
            ->selectRaw('COALESCE(SUM(sale_items.price * (sale_items.qty - sale_items.returned_qty)), 0) as revenue_sum')
            ->selectRaw('COALESCE(SUM(CASE WHEN sale_items.buy_price IS NULL THEN 0 ELSE (sale_items.price - sale_items.buy_price) * (sale_items.qty - sale_items.returned_qty) END), 0) as profit_sum')
            ->groupBy('sale_items.product_id', 'sale_items.name')
            ->orderByDesc(['revenue' => 'revenue_sum', 'qty' => 'qty_sum', 'profit' => 'profit_sum'][$by])
            ->limit($limit)
            ->get();

        return [
            'from' => $from, 'to' => $to, 'sorted_by' => $by,
            'items' => $rows->map(fn ($r) => [
                'name' => $r->product_name,
                'qty' => (float) $r->qty_sum,
                'revenue' => round((float) $r->revenue_sum, 2),
                'profit' => round((float) $r->profit_sum, 2),
            ])->values()->all(),
        ];
    }

    private function overdueDebts(User $user): array
    {
        $debts = Debt::forUser($user)->overdue()->with('customer')->orderBy('due_date')->limit(20)->get();

        return [
            'count' => $debts->count(),
            'total' => round($debts->sum(fn (Debt $d) => (float) $d->remaining), 2),
            'items' => $debts->map(fn (Debt $d) => [
                'customer' => $d->customer?->name,
                'remaining' => (float) $d->remaining,
                'due_date' => $d->due_date?->toDateString(),
            ])->values()->all(),
        ];
    }

    private function lowStock(User $user, array $input): array
    {
        $query = Product::forUser($user)->active();

        if (isset($input['threshold']) && is_numeric($input['threshold'])) {
            $query->where('stock', '<', (float) $input['threshold']);
        } else {
            $query->needsAttention();
        }

        $products = $query->orderBy('stock')->limit(30)->get();

        return [
            'count' => $products->count(),
            'items' => $products->map(fn (Product $p) => [
                'name' => $p->name, 'stock' => (float) $p->stock, 'min_stock' => (float) $p->min_stock, 'unit' => $p->unit,
            ])->values()->all(),
        ];
    }

    private function customerDebt(User $user, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '') {
            return ['error' => 'Ism kerak'];
        }

        $customers = Customer::forUser($user)->where('name', 'like', '%'.addcslashes($name, '%_').'%')->limit(5)->get();

        return [
            'matches' => $customers->map(fn (Customer $c) => [
                'name' => $c->name,
                'phone' => $c->phone,
                'balance' => (float) $c->balance,
            ])->values()->all(),
        ];
    }

    private function expensesSummary(User $user, array $input): array
    {
        [$from, $to] = $this->range($input);

        $byCategory = Expense::forUser($user)->between($from, $to)
            ->selectRaw('category, SUM(amount) as total')->groupBy('category')->pluck('total', 'category');

        return [
            'from' => $from, 'to' => $to,
            'total' => round((float) $byCategory->sum(), 2),
            'by_category' => $byCategory->map(fn ($v) => round((float) $v, 2))->all(),
        ];
    }
}
