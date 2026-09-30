<?php

namespace App\Services\Currency;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Valyuta kurslari — O'zbekiston Markaziy banki (cbu.uz) rasmiy kurslari.
 * Kesh: TTL tugagach qayta yuklanadi; manba ishlamasa oxirgi saqlangan kurs qaytadi.
 */
class CurrencyService
{
    private const FRESH_KEY = 'currency_rates.fresh';

    private const STALE_KEY = 'currency_rates.stale';

    /** @return array{updated_at: ?string, source: string, rates: list<array<string, mixed>>} */
    public function rates(): array
    {
        $fresh = Cache::get(self::FRESH_KEY);

        if ($fresh !== null) {
            return $fresh;
        }

        return $this->refresh() ?? Cache::get(self::STALE_KEY) ?? [
            'updated_at' => null,
            'source' => 'cbu.uz',
            'rates' => [],
        ];
    }

    /** CBU'dan yangi kurslarni yuklab keshlaydi; xatolikda null */
    public function refresh(): ?array
    {
        try {
            $response = Http::timeout(10)->acceptJson()->get(config('savdodaftar.currency.url'));

            if (! $response->successful() || ! is_array($response->json())) {
                return null;
            }

            $wanted = array_map('strtoupper', config('savdodaftar.currency.codes'));

            $rates = collect($response->json())
                ->filter(fn ($row) => in_array($row['Ccy'] ?? null, $wanted, true))
                ->sortBy(fn ($row) => array_search($row['Ccy'], $wanted, true))
                ->map(fn ($row) => [
                    'code' => $row['Ccy'],
                    'name' => $row['CcyNm_UZ'] ?? $row['Ccy'],
                    'name_ru' => $row['CcyNm_RU'] ?? $row['Ccy'],
                    'nominal' => (int) ($row['Nominal'] ?? 1),
                    'rate' => (float) $row['Rate'],
                    'diff' => (float) ($row['Diff'] ?? 0),
                    'date' => $row['Date'] ?? null,
                ])
                ->values()
                ->all();

            if ($rates === []) {
                return null;
            }

            $data = ['updated_at' => now()->toIso8601String(), 'source' => 'cbu.uz', 'rates' => $rates];

            Cache::put(self::FRESH_KEY, $data, (int) config('savdodaftar.currency.ttl'));
            Cache::forever(self::STALE_KEY, $data);

            return $data;
        } catch (Throwable $e) {
            Log::warning('CBU kurslarini yuklashda xatolik: '.$e->getMessage());

            return null;
        }
    }
}
