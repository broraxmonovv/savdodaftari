<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;

/** Bosh sahifadagi reklama karuseli */
class BannerController extends Controller
{
    use RespondsWithJson;

    /** GET /banners — hozir ko'rsatilishi kerak bo'lgan bannerlar */
    public function index(): JsonResponse
    {
        $banners = Banner::visible()->orderBy('sort')->orderByDesc('id')->get()
            ->map(fn (Banner $banner) => [
                'id' => $banner->id,
                'title' => $banner->title,
                'url' => $banner->url,
                'image_url' => $banner->image_url,
            ])->values();

        return $this->success($banners);
    }

    /** POST /banners/{id}/click — bosishlar sonini hisoblash */
    public function click(int $id): JsonResponse
    {
        Banner::visible()->whereKey($id)->increment('clicks');

        return $this->success(null);
    }
}
