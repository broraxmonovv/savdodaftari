<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            // Qaytarish muddati kuni (soat 09:00) yuborilgan SMS sanasi
            $table->date('due_today_sms_at')->nullable()->after('due_soon_sms_at');
        });
    }

    public function down(): void
    {
        Schema::table('debts', fn (Blueprint $table) => $table->dropColumn('due_today_sms_at'));
    }
};
