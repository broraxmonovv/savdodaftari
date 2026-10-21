<?php

namespace App\Console\Commands;

use App\Models\Banner;
use App\Support\ImageOptimizer;
use Illuminate\Console\Command;

/** Avval yuklangan katta banner rasmlarini siqadi (tezroq ochilishi uchun) */
class OptimizeBanners extends Command
{
    protected $signature = 'banners:optimize';

    protected $description = 'Mavjud reklama rasmlarini kichraytirib siqadi';

    public function handle(): int
    {
        $done = 0;

        Banner::each(function (Banner $banner) use (&$done) {
            $new = ImageOptimizer::recompress($banner->image_path, Banner::disk());

            if ($new !== null) {
                $banner->update(['image_path' => $new]);
                $done++;
            }
        });

        $this->info("Siqildi: {$done} ta rasm.");

        return self::SUCCESS;
    }
}
