<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** FCM qurilma tokenini ro'yxatdan o'tkazish / o'chirish */
class DeviceController extends Controller
{
    use RespondsWithJson;

    /** POST /devices {token, platform?} — token shu foydalanuvchiga biriktiriladi (qurilma boshqa akkauntga o'tsa ham) */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', Rule::in(['android', 'ios'])],
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => $request->user()->id, 'platform' => $data['platform'] ?? null, 'last_seen_at' => now()],
        );

        return $this->success(message: 'OK', status: 201);
    }

    /** DELETE /devices {token} — chiqishda */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);

        DeviceToken::forUser($request->user())->where('token', $data['token'])->delete();

        return $this->success();
    }
}
