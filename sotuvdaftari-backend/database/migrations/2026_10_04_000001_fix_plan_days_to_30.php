<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Barcha tarif muddati 30 kun (admin panelda endi o'zgartirilmaydi) */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('plans')->update(['days' => 30]);
    }

    public function down(): void
    {
        // Oldingi qiymatlar saqlanmagan
    }
};
