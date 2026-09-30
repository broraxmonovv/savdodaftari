<?php

namespace App\Services\Export;

use App\Models\Debt;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Ai\AssistantTools;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Pro: hisobotlarni Excel (.xlsx) va PDF ga eksport qilish (TZ 17, 35).
 * Har bir tur bir nechta "bo'lim"dan iborat: [title, headers, rows]; ikkala format shundan yasaladi.
 */
class ExportService
{
    public const TYPES = ['report', 'sales', 'expenses', 'debts', 'inventory'];

    private const ROW_LIMIT = 5000;

    public function __construct(private readonly AssistantTools $tools) {}

    /**
     * @return array{title: string, subtitle: string, sections: list<array{title: string, headers: list<string>, rows: list<list<string|int|float>>}>}
     */
    public function build(User $user, string $type, string $from, string $to): array
    {
        $sections = match ($type) {
            'sales' => [$this->sales($user, $from, $to)],
            'expenses' => [$this->expenses($user, $from, $to)],
            'debts' => [$this->debts($user)],
            'inventory' => [$this->inventory($user)],
            default => $this->report($user, $from, $to),
        };

        $period = in_array($type, ['debts', 'inventory'], true)
            ? now()->format('d.m.Y')
            : date('d.m.Y', strtotime($from)).' — '.date('d.m.Y', strtotime($to));

        return [
            'title' => __('export.types.'.$type),
            'subtitle' => __('export.shop').': '.($user->shop_name ?: $user->name ?: $user->phone)
                .' · '.__('export.period').': '.$period
                .' · '.__('export.generated').': '.now()->format('d.m.Y H:i'),
            'sections' => $sections,
        ];
    }

    public function xlsx(array $document, string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        $bold = (new Style)->setFontBold();
        $first = true;

        foreach ($document['sections'] as $section) {
            if ($first) {
                $writer->getCurrentSheet()->setName($this->sheetName($section['title']));
                $first = false;
            } else {
                $writer->addNewSheetAndMakeItCurrent()->setName($this->sheetName($section['title']));
            }

            $writer->addRow(Row::fromValuesWithStyles([$document['title'].' — '.$section['title']], [$bold]));
            $writer->addRow(Row::fromValues([$document['subtitle']]));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValuesWithStyles($section['headers'], array_fill(0, count($section['headers']), $bold)));

            foreach ($section['rows'] as $row) {
                $writer->addRow(Row::fromValues($row));
            }

            if ($section['rows'] === []) {
                $writer->addRow(Row::fromValues([__('export.empty')]));
            }
        }

        $writer->close();
    }

    public function pdf(array $document): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('tempDir', sys_get_temp_dir());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(View::make('exports.pdf', ['document' => $document])->render(), 'UTF-8');
        $dompdf->setPaper('A4', count($document['sections'][0]['headers'] ?? []) > 6 ? 'landscape' : 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function sheetName(string $title): string
    {
        // Excel varag'i nomi: maks. 31 belgi, [ ] : * ? / \ taqiqlangan
        return mb_substr(preg_replace('/[\[\]:*?\/\\\\]/u', ' ', $title), 0, 31);
    }

    private function money(float|string|null $value): float
    {
        return round((float) $value, 2);
    }

    /** @return list<array<string, mixed>> */
    private function report(User $user, string $from, string $to): array
    {
        $summary = $this->tools->run($user, 'sales_summary', ['from' => $from, 'to' => $to]);
        $top = $this->tools->run($user, 'top_products', ['from' => $from, 'to' => $to, 'by' => 'revenue', 'limit' => 10]);

        $daily = Sale::forUser($user)->between($from, $to)
            ->selectRaw('DATE(sold_at) as d, COUNT(*) as c, SUM(total - returned_total) as revenue,'
                .' SUM(COALESCE(profit, 0) - (returned_total - returned_cost)) as profit')
            ->groupBy('d')->orderBy('d')->get();

        $expensesByDay = Expense::forUser($user)->between($from, $to)
            ->selectRaw('DATE(spent_at) as d, SUM(amount) as total')->groupBy('d')->pluck('total', 'd');

        return [
            [
                'title' => __('export.summary'),
                'headers' => [__('export.period'), ''],
                'rows' => [
                    [__('export.sales_count'), $summary['sales_count']],
                    [__('export.revenue'), $summary['total_revenue']],
                    [__('export.gross_profit'), $summary['gross_profit']],
                    [__('export.expenses'), $summary['expenses']],
                    [__('export.net_profit'), $summary['net_profit']],
                    [__('export.cash'), $summary['cash']],
                    [__('export.card'), $summary['card']],
                    [__('export.debt'), $summary['on_debt']],
                ],
            ],
            [
                'title' => __('export.daily'),
                'headers' => [__('export.date'), __('export.sales_count'), __('export.revenue'), __('export.profit'), __('export.expenses')],
                'rows' => $daily->map(fn ($r) => [
                    date('d.m.Y', strtotime($r->d)), (int) $r->c, $this->money($r->revenue), $this->money($r->profit),
                    $this->money($expensesByDay[$r->d] ?? 0),
                ])->values()->all(),
            ],
            [
                'title' => __('export.top_products'),
                'headers' => [__('export.name'), __('export.qty'), __('export.revenue'), __('export.profit')],
                'rows' => collect($top['items'])->map(fn ($i) => [$i['name'], $i['qty'], $i['revenue'], $i['profit']])->values()->all(),
            ],
        ];
    }

    private function sales(User $user, string $from, string $to): array
    {
        $rows = Sale::forUser($user)->between($from, $to)->with('customer')->orderBy('sold_at')->limit(self::ROW_LIMIT)->get();

        return [
            'title' => __('export.types.sales'),
            'headers' => [__('export.date'), __('export.customer'), __('export.method'), __('export.total'), __('export.discount'), __('export.debt'), __('export.profit'), __('export.status')],
            'rows' => $rows->map(fn (Sale $s) => [
                $s->sold_at->format('d.m.Y H:i'),
                $s->customer?->name ?? '—',
                __('messages.sale.methods.'.$s->payment_method),
                $this->money($s->total), $this->money($s->discount), $this->money($s->debt_amount), $this->money($s->profit),
                $s->status,
            ])->values()->all(),
        ];
    }

    private function expenses(User $user, string $from, string $to): array
    {
        $rows = Expense::forUser($user)->between($from, $to)->orderBy('spent_at')->limit(self::ROW_LIMIT)->get();

        return [
            'title' => __('export.types.expenses'),
            'headers' => [__('export.date'), __('export.category'), __('export.amount'), __('export.note')],
            'rows' => $rows->map(fn (Expense $e) => [
                $e->spent_at->format('d.m.Y'), $e->category, $this->money($e->amount), $e->note ?? '',
            ])->values()->all(),
        ];
    }

    private function debts(User $user): array
    {
        $rows = Debt::forUser($user)->unpaid()->with('customer')->orderBy('due_date')->limit(self::ROW_LIMIT)->get();

        return [
            'title' => __('export.types.debts'),
            'headers' => [__('export.customer'), __('export.issued'), __('export.amount'), __('export.paid'), __('export.remaining'), __('export.due')],
            'rows' => $rows->map(fn (Debt $d) => [
                $d->customer?->name ?? '—',
                $d->issued_at?->format('d.m.Y') ?? '',
                $this->money($d->amount), $this->money($d->paid_amount), $this->money($d->remaining),
                $d->due_date?->format('d.m.Y') ?? '',
            ])->values()->all(),
        ];
    }

    private function inventory(User $user): array
    {
        $rows = Product::forUser($user)->active()->orderBy('name')->limit(self::ROW_LIMIT)->get();

        return [
            'title' => __('export.types.inventory'),
            'headers' => [__('export.name'), __('export.category'), __('export.unit'), __('export.stock'), __('export.min_stock'), __('export.buy_price'), __('export.sell_price'), __('export.stock_value')],
            'rows' => $rows->map(fn (Product $p) => [
                $p->name, $p->category ?? '', $p->unit, (float) $p->stock, (float) $p->min_stock,
                $this->money($p->buy_price), $this->money($p->sell_price),
                $this->money((float) $p->stock * (float) ($p->buy_price ?? 0)),
            ])->values()->all(),
        ];
    }
}
