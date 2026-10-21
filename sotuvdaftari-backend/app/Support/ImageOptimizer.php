<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Yuklangan rasmlarni ilovada tez ochiladigan qilib kichraytiradi va siqadi.
 *
 * Telefon kamerasi/dizayner rasmlari 3–8 MB bo'lishi mumkin; reklama karuseli esa ~400 px eniga
 * (retina uchun 1200 px) yetadi. Rasm eni [maxWidth] dan oshmasligi uchun kichraytiriladi va
 * WebP (yoki JPEG) ga o'tkaziladi — odatda 10–30 baravar kichik fayl. GD bo'lmasa yoki rasm
 * o'qilmasa, asl fayl o'zgarishsiz saqlanadi.
 */
class ImageOptimizer
{
    public const DEFAULT_MAX_WIDTH = 1200;

    public static function store(UploadedFile $file, string $directory, string $disk, int $maxWidth = self::DEFAULT_MAX_WIDTH, int $quality = 80): string
    {
        $encoded = self::encode((string) file_get_contents($file->getRealPath()), $maxWidth, $quality);

        if ($encoded === null) {
            return $file->store($directory, $disk);
        }

        $path = trim($directory, '/').'/'.Str::random(40).'.'.$encoded['ext'];
        Storage::disk($disk)->put($path, $encoded['data'], 'public');

        return $path;
    }

    /** Diskdagi mavjud rasmni siqib qayta yozadi; yangi yo'lni qaytaradi (o'zgarmasa null) */
    public static function recompress(string $path, string $disk, int $maxWidth = self::DEFAULT_MAX_WIDTH, int $quality = 80): ?string
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            return null;
        }

        $original = $storage->get($path);
        $encoded = self::encode($original, $maxWidth, $quality);

        if ($encoded === null || strlen($encoded['data']) >= strlen($original) * 0.9) {
            return null;
        }

        $new = trim(dirname($path), '/.').'/'.Str::random(40).'.'.$encoded['ext'];
        $storage->put($new, $encoded['data'], 'public');
        $storage->delete($path);

        return $new;
    }

    /** @return array{data: string, ext: string}|null */
    public static function encode(string $binary, int $maxWidth, int $quality): ?array
    {
        if (! function_exists('imagecreatefromstring') || $binary === '') {
            return null;
        }

        $info = @getimagesizefromstring($binary);

        // Animatsiyali GIF va juda katta rasmlar (xotira) o'zgarishsiz qoladi
        if ($info === false || $info[2] === IMAGETYPE_GIF || $info[0] * $info[1] > 40_000_000) {
            return null;
        }

        @ini_set('memory_limit', '512M');
        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            return null;
        }

        $image = self::applyExifOrientation($image, $binary);
        $width = imagesx($image);

        if ($width > $maxWidth) {
            $scaled = imagescale($image, $maxWidth, -1, IMG_BICUBIC);

            if ($scaled !== false) {
                imagedestroy($image);
                $image = $scaled;
            }
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        if (function_exists('imagewebp')) {
            imagewebp($image, null, $quality);
            $ext = 'webp';
        } else {
            // JPEG shaffoflikni qo'llamaydi — oq fon ustiga
            $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            imagejpeg($flat, null, $quality);
            imagedestroy($flat);
            $ext = 'jpg';
        }
        $data = (string) ob_get_clean();
        imagedestroy($image);

        return $data === '' ? null : ['data' => $data, 'ext' => $ext];
    }

    private static function applyExifOrientation(\GdImage $image, string $binary): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $binary);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);

        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle !== 0 && ($rotated = imagerotate($image, $angle, 0)) !== false) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }
}
