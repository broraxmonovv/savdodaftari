<?php

use Illuminate\Support\Facades\Schedule;

// Eski OTP kodlarini har kuni tozalash (App\Models\OtpCode::prunable)
Schedule::command('model:prune')->daily();

// Valyuta kurslarini cbu.uz dan har soatda yangilash
Schedule::command('rates:refresh')->hourly();

// Kunlik push eslatmalar (har kuni 09:00)
Schedule::command('push:reminders')->dailyAt('09:00');

// Bepul sinov tugashi bildirishnomalari (har soatda)
Schedule::command('trial:notify')->hourly();
