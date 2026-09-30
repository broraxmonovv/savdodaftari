<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\User;
use App\Services\Push\PushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/** Admin e'lonini auditoriyaga push qilib yuboradi (javobdan keyin, katta auditoriya bo'laklab) */
class SendAnnouncementPush implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $announcementId) {}

    public function handle(PushService $push): void
    {
        $announcement = Announcement::find($this->announcementId);

        if ($announcement === null) {
            return;
        }

        $users = User::query()->whereHas('deviceTokens')->whereNull('blocked_at')
            ->when($announcement->audience === Announcement::AUDIENCE_USER, fn ($q) => $q->whereKey($announcement->user_id))
            ->when($announcement->audience === Announcement::AUDIENCE_PLAN && $announcement->plan === 'free',
                fn ($q) => $q->whereDoesntHave('subscriptions', fn ($s) => $s->active()))
            ->when($announcement->audience === Announcement::AUDIENCE_PLAN && $announcement->plan !== 'free',
                fn ($q) => $q->whereHas('subscriptions', fn ($s) => $s->active()->where('plan', $announcement->plan)));

        $users->chunkById(200, function ($chunk) use ($push, $announcement) {
            foreach ($chunk as $user) {
                $push->toUser($user, $announcement->title, $announcement->body, [
                    'type' => 'announcement',
                    'announcement_id' => (string) $announcement->id,
                ]);
            }
        });
    }
}
