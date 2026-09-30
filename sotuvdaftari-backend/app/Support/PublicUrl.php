<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Yuklangan fayllar (banner, profil, mahsulot rasmi) uchun URL.
 *
 * Mahalliy `public` diskda URL joriy so'rov manzilidan (host + port) yasaladi, shuning uchun
 * `APP_URL` noto'g'ri yoki port yo'q bo'lsa ham (masalan `php artisan serve`, telefondan IP orqali)
 * rasm ochiladi. Boshqa disklar (S3 va h.k.) uchun odatdagi `Storage::url()` ishlatiladi.
 */
class PublicUrl
{
    public static function for(string $disk, ?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if ($disk === 'public') {
            // `storage:link` bajarilgan bo'lsa web-server faylni o'zi beradi, aks holda zaxira `media/` marshruti
            $prefix = file_exists(public_path('storage')) ? 'storage' : 'media';

            return asset($prefix.'/'.ltrim($path, '/'));
        }

        return Storage::disk($disk)->url($path);
    }
}
