<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Services\Currency\CurrencyService;
use Illuminate\Http\JsonResponse;

/** Ilova ichidagi ma'lumot bo'limlari: valyuta kurslari, qo'llab-quvvatlash, qo'llanma videolar */
class InfoController extends Controller
{
    use RespondsWithJson;

    /** GET /currencies — cbu.uz rasmiy kurslari (keshlanadi) */
    public function currencies(CurrencyService $currencies): JsonResponse
    {
        return $this->success($currencies->rates());
    }

    /** GET /support */
    public function support(): JsonResponse
    {
        return $this->success(config('savdodaftar.support'));
    }

    /** GET /guides — tilga mos sarlavhali videolar ro'yxati */
    public function guides(): JsonResponse
    {
        $ru = app()->getLocale() === 'ru';

        return $this->success(collect(config('savdodaftar.guides'))->map(fn (array $g) => [
            'id' => $g['id'],
            'title' => ($ru ? ($g['title_ru'] ?? null) : null) ?? $g['title_uz'] ?? $g['id'],
            'url' => $g['url'],
        ])->values());
    }
}
