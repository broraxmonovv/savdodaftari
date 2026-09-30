<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Services\Ai\VoiceCommandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pro: ovozli boshqaruv va AI yordamchi (TZ 8, 24) */
class AiController extends Controller
{
    use RespondsWithJson;

    /**
     * POST /ai/voice {text} — ovozdan olingan matnni tahlil qiladi.
     * Yozuvchi buyruqlar `needs_confirmation: true` bilan qaytadi va ilova tasdiqlagach bajaradi.
     */
    public function voice(Request $request, VoiceCommandService $voice): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:300']]);

        return $this->success($voice->handle($request->user(), $data['text']));
    }
}
