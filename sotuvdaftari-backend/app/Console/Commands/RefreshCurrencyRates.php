<?php

namespace App\Console\Commands;

use App\Services\Currency\CurrencyService;
use Illuminate\Console\Command;

class RefreshCurrencyRates extends Command
{
    protected $signature = 'rates:refresh';

    protected $description = 'cbu.uz dan valyuta kurslarini yangilaydi';

    public function handle(CurrencyService $currencies): int
    {
        if ($currencies->refresh() === null) {
            $this->error('Kurslarni yuklab bo\'lmadi.');

            return self::FAILURE;
        }

        $this->info('Kurslar yangilandi.');

        return self::SUCCESS;
    }
}
