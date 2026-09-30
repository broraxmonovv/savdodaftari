<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use App\Models\User;

/** Foydalanuvchilarning barcha qurilmalariga push yuboradi */
class PushService
{
    public function __construct(private readonly FcmClient $fcm) {}

    /** @param  array<string, string>  $data */
    public function toUser(User $user, string $title, string $body, array $data = []): int
    {
        if (! $this->fcm->isConfigured() || $user->isBlocked()) {
            return 0;
        }

        $sent = 0;

        foreach (DeviceToken::forUser($user)->pluck('token') as $token) {
            $sent += $this->fcm->send($token, $title, $body, $data) ? 1 : 0;
        }

        return $sent;
    }
}
