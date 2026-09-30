<?php

namespace App\Console\Commands;

use App\Jobs\SendAnnouncementPush;
use App\Models\Announcement;
use App\Models\Subscription;
use Illuminate\Console\Command;

/**
 * Bepul Standart sinov bildirishnomalari (har soatda, idempotent):
 *  - tugashiga 2 kun qolganda: "sinov tugayapti, 30 kunga cho'zish uchun to'lang";
 *  - tugaganda: "sinov tugadi, Standart/Pro funksiyalari o'chdi".
 * Bildirishnoma ilova ichida (e'lon) va push sifatida yuboriladi.
 */
class NotifyTrialEnding extends Command
{
    protected $signature = 'trial:notify';

    protected $description = 'Bepul sinov tugashi haqida bildirishnoma yuboradi';

    public function handle(): int
    {
        $ending = 0;
        $ended = 0;

        Subscription::query()->with('user')->where('is_trial', true)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(2))
            ->whereNull('ending_notified_at')
            ->each(function (Subscription $sub) use (&$ending) {
                $this->notify($sub, 'ending');
                $sub->update(['ending_notified_at' => now()]);
                $ending++;
            });

        Subscription::query()->with('user')->where('is_trial', true)
            ->where('expires_at', '<=', now())
            ->whereNull('ended_notified_at')
            ->each(function (Subscription $sub) use (&$ended) {
                $this->notify($sub, 'ended');
                $sub->update(['ended_notified_at' => now(), 'status' => Subscription::STATUS_EXPIRED]);
                $ended++;
            });

        $this->info("Sinov tugayapti: {$ending}, tugadi: {$ended}.");

        return self::SUCCESS;
    }

    private function notify(Subscription $sub, string $kind): void
    {
        $user = $sub->user;

        if ($user === null) {
            return;
        }

        $locale = $user->locale ?: 'uz';
        $params = [
            'days' => (int) config('savdodaftar.trial.days'),
            'date' => $sub->expires_at->format('d.m.Y'),
            'price' => number_format((float) \App\Models\Plan::where('key', 'standard')->value('price'), 0, '.', ' '),
        ];

        $announcement = Announcement::create([
            'title' => __("messages.trial.{$kind}_title", [], $locale),
            'body' => __("messages.trial.{$kind}_body", $params, $locale),
            'audience' => Announcement::AUDIENCE_USER,
            'user_id' => $user->id,
        ]);

        SendAnnouncementPush::dispatch($announcement->id);
    }
}
