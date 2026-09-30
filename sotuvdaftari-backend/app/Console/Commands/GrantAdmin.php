<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantAdmin extends Command
{
    protected $signature = 'admin:grant {phone : +998901234567} {--password= : Admin panel paroli (kamida 8 belgi)} {--revoke : Admin huquqini olib tashlash}';

    protected $description = 'Foydalanuvchiga admin huquqi va admin panel paroli beradi';

    public function handle(): int
    {
        $user = User::where('phone', $this->argument('phone'))->first();

        if ($user === null) {
            $this->error('Foydalanuvchi topilmadi.');

            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $user->forceFill(['is_admin' => false, 'admin_password' => null])->save();
            $this->info('Admin huquqi olib tashlandi.');

            return self::SUCCESS;
        }

        $password = $this->option('password') ?: $this->secret('Admin panel paroli (kamida 8 belgi)');

        if (strlen((string) $password) < 8) {
            $this->error('Parol kamida 8 belgidan iborat bo\'lishi kerak.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => true, 'admin_password' => $password])->save();
        $this->info("Admin huquqi berildi. Panel: /admin (login: {$user->phone}).");

        return self::SUCCESS;
    }
}
