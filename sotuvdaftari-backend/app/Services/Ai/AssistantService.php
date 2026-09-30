<?php

namespace App\Services\Ai;

use App\Exceptions\ApiException;
use App\Models\User;

/**
 * AI biznes yordamchi (TZ 24, Pro): foydalanuvchi savoliga Claude javob beradi va kerak bo'lsa
 * [AssistantTools] (faqat o'qish) orqali foydalanuvchining haqiqiy ma'lumotlarini so'raydi.
 * Yozuvchi amallarni (qarz yozish va h.k.) bajarmaydi — ular ovozli boshqaruvda tasdiq bilan bajariladi.
 */
class AssistantService
{
    private const MAX_STEPS = 5;

    public function __construct(
        private readonly ClaudeClient $claude,
        private readonly AssistantTools $tools,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $history  oldingi xabarlar (faqat matn)
     * @return array{reply: string, tools_used: list<string>}
     */
    public function chat(User $user, string $message, array $history = []): array
    {
        $messages = [];

        foreach (array_slice($history, -10) as $item) {
            if (in_array($item['role'] ?? null, ['user', 'assistant'], true) && filled($item['content'] ?? null)) {
                $messages[] = ['role' => $item['role'], 'content' => (string) $item['content']];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $used = [];

        for ($step = 0; $step < self::MAX_STEPS; $step++) {
            $response = $this->claude->message(
                $messages,
                $this->systemPrompt($user),
                maxTokens: 1200,
                tools: $this->tools->definitions(),
            );

            if (($response['stop_reason'] ?? null) !== 'tool_use') {
                $reply = $this->claude->text($response);

                if ($reply === '') {
                    throw new ApiException(__('messages.ai.failed'), 502, 'ai_failed');
                }

                return ['reply' => $reply, 'tools_used' => array_values(array_unique($used))];
            }

            // Modelning vosita so'rovlarini bajarib, natijani qaytaramiz
            $messages[] = ['role' => 'assistant', 'content' => $response['content']];

            $results = [];

            foreach ($response['content'] as $block) {
                if (($block['type'] ?? null) !== 'tool_use') {
                    continue;
                }

                $used[] = $block['name'];
                $results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $block['id'],
                    'content' => json_encode(
                        $this->tools->run($user, (string) $block['name'], (array) ($block['input'] ?? [])),
                        JSON_UNESCAPED_UNICODE,
                    ),
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $results];
        }

        throw new ApiException(__('messages.ai.failed'), 502, 'ai_failed');
    }

    private function systemPrompt(User $user): string
    {
        $language = app()->getLocale() === 'ru' ? 'rus tilida' : "o'zbek tilida (lotin yozuvi)";

        return implode("\n", [
            "Sen BozorPro ilovasining biznes yordamchisisan: bozorchi va kichik do'kon egalariga ularning savdo, qarz, ombor va xarajatlari bo'yicha savollarga javob berasan.",
            "Javobni {$language}, qisqa va sodda yoz. Pul summalarini \"150 000 so'm\" ko'rinishida yoz.",
            "Bugungi sana: ".today()->toDateString().'. "Bugun", "kecha", "bu hafta", "o\'tgan oy" kabi davrlarni shu sanadan hisobla va vositalarga Y-m-d sanalar ber.',
            "Raqamlarni FAQAT vositalar qaytargan ma'lumotdan ol; taxmin qilma va o'ylab topma. Ma'lumot bo'lmasa, shuni ayt.",
            "Faqat shu foydalanuvchining ma'lumotlari bilan ishlaysan; boshqa mavzular (siyosat, kod, umumiy suhbat) bo'yicha muloyimlik bilan rad et.",
            "Sen ma'lumotni o'zgartira olmaysan. Qarz yozish, to'lov qabul qilish yoki kirim qilish so'ralsa, bosh sahifadagi mikrofon (ovozli boshqaruv) orqali qilishni tavsiya et.",
            "Do'kon: ".($user->shop_name ?: '—').'.',
        ]);
    }
}
