<?php

use Illuminate\Support\Facades\Schedule;

// Eski OTP kodlarini har kuni tozalash (App\Models\OtpCode::prunable)
Schedule::command('model:prune')->daily();

// Valyuta kurslarini cbu.uz dan har soatda yangilash
Schedule::command('rates:refresh')->hourly();
