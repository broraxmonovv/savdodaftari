<?php

namespace App\Services\Ai;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Claude Messages API uchun yupqa klient (rasm tahlili va yordamchi suhbat) */
class ClaudeClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array<string, mixed> API javobi (content bloklari bilan)
     */
    public function message(array $messages, ?string $system = null, int $maxTokens = 2000, array $tools = []): array
    {
        if (! $this->isConfigured()) {
            throw new ApiException(__('messages.ai.not_configured'), 503, 'ai_not_configured');
        }

        $payload = array_filter([
            'model' => config('services.anthropic.model'),
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => $messages,
            'tools' => $tools ?: null,
        ], fn ($v) => $v !== null);

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
            ])->timeout(90)->acceptJson()
                ->post(rtrim(config('services.anthropic.base_url'), '/').'/v1/messages', $payload);
        } catch (Throwable $e) {
            Log::warning('Claude API ulanish xatosi: '.$e->getMessage());

            throw new ApiException(__('messages.ai.failed'), 502, 'ai_failed');
        }

        if (! $response->successful()) {
            Log::warning('Claude API xatosi: '.$response->status().' '.$response->body());

            throw new ApiException(__('messages.ai.failed'), 502, 'ai_failed');
        }

        return $response->json();
    }

    /** Javobdagi barcha matn bloklarini birlashtiradi */
    public function text(array $response): string
    {
        return trim(collect($response['content'] ?? [])
            ->where('type', 'text')->pluck('text')->implode("\n"));
    }
}
