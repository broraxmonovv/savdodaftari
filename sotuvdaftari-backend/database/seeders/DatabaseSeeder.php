<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Takroran ishga tushirilsa ham xato bermaydi (demo foydalanuvchi bir marta yaratiladi). */
    public function run(): void
    {
        if (User::where('phone', '+998901234567')->exists()) {
            return;
        }

        User::factory()->create([
            'phone' => '+998901234567',
            'name' => 'Demo foydalanuvchi',
            'shop_name' => 'Demo do\'kon',
            'pin' => '1234',
        ]);
    }
}
