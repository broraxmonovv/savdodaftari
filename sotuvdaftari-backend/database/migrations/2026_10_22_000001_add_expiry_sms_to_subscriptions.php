<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Tugashiga 1 kun qolganda SMS + bildirishnoma yuborilgan vaqt (har obuna uchun bir marta)
            $table->timestamp('expiry_reminded_at')->nullable()->after('ended_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn('expiry_reminded_at'));
    }
};
