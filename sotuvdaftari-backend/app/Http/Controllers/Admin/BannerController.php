<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Reklama bannerlari: rasm, sarlavha, havola, ko'rsatish muddati, o'chirib qo'yish va o'chirish */
class BannerController extends Controller
{
    public function index()
    {
        return view('admin.banners', ['banners' => Banner::orderBy('sort')->orderByDesc('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'url' => ['required', 'url:http,https', 'max:500'],
            'image' => ['required', 'image', 'max:'.config('savdodaftar.banners.image_max_kb')],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        Banner::create([
            'title' => $data['title'],
            'url' => $data['url'],
            'image_path' => $request->file('image')->store('banners', Banner::disk()),
            'is_active' => true,
            'starts_at' => now(),
            // Muddat bo'sh qoldirilsa — cheksiz
            'ends_at' => filled($data['days'] ?? null) ? now()->addDays((int) $data['days']) : null,
            'sort' => (int) ($data['sort'] ?? 0),
        ]);

        return back()->with('status', 'Banner qo\'shildi.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $banner = Banner::findOrFail($id);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'url' => ['sometimes', 'required', 'url:http,https', 'max:500'],
            'image' => ['nullable', 'image', 'max:'.config('savdodaftar.banners.image_max_kb')],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($request->hasFile('image')) {
            Storage::disk(Banner::disk())->delete($banner->image_path);
            $banner->image_path = $request->file('image')->store('banners', Banner::disk());
        }

        // "Necha kun" berilsa, muddat bugundan boshlab qayta hisoblanadi
        if (filled($data['days'] ?? null)) {
            $banner->starts_at = now();
            $banner->ends_at = now()->addDays((int) $data['days']);
        }

        $banner->fill(collect($data)->only(['title', 'url', 'sort', 'is_active'])->all())->save();

        return back()->with('status', 'Banner yangilandi.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $banner = Banner::findOrFail($id);
        Storage::disk(Banner::disk())->delete($banner->image_path);
        $banner->delete();

        return back()->with('status', 'Banner o\'chirildi.');
    }
}
