<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantAdmin extends Command
{
    protected $signature = 'admin:grant {phone : +998901234567} {--revoke : Admin huquqini olib tashlash}';

    protected $description = 'Foydalanuvchiga admin huquqi beradi (yechib olish so\'rovlarini boshqarish uchun)';

    public function handle(): int
    {
        $user = User::where('phone', $this->argument('phone'))->first();

        if ($user === null) {
            $this->error('Foydalanuvchi topilmadi.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();
        $this->info($user->is_admin ? 'Admin huquqi berildi.' : 'Admin huquqi olib tashlandi.');

        return self::SUCCESS;
    }
}
