<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Services\Ai\AssistantService;
use App\Services\Ai\OcrImportService;
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

    /**
     * POST /ai/ocr-import (multipart `image`) — eski daftar rasmini tahlil qiladi.
     * Hech narsa yozilmaydi: foydalanuvchi natijani tekshirib /ai/ocr-import/confirm bilan tasdiqlaydi.
     */
    public function ocr(Request $request, OcrImportService $ocr): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192']]);

        $items = $ocr->extract($request->user(), $request->file('image'));

        return $this->success([
            'items' => $items,
            'count' => count($items),
            'uncertain_count' => count(array_filter($items, fn ($i) => $i['uncertain'])),
        ]);
    }

    /** POST /ai/ocr-import/confirm {items: [{name, amount, phone?, note?}]} — tasdiqlangan qatorlarni yozadi */
    public function ocrConfirm(Request $request, OcrImportService $ocr): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.name' => ['required', 'string', 'min:1', 'max:100'],
            'items.*.amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'items.*.phone' => ['nullable', 'string', 'max:20'],
            'items.*.note' => ['nullable', 'string', 'max:200'],
        ]);

        return $this->success($ocr->import($request->user(), $data['items']), __('messages.ai.ocr_imported'), 201);
    }

    /**
     * POST /ai/assistant {message, history?: [{role: user|assistant, content}]} — AI biznes yordamchi (Pro).
     * Javob: {reply, tools_used}. Stateless: ilova oxirgi xabarlarni `history` da yuboradi.
     */
    public function assistant(Request $request, AssistantService $assistant): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:4000'],
        ]);

        return $this->success($assistant->chat($request->user(), $data['message'], $data['history'] ?? []));
    }
}
