<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Yangi foydalanuvchilar uchun bepul Standart sinov davri (bir marta) va uning bildirishnomalari */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Sinov berilgan vaqt — qayta berilmasligi uchun
            $table->timestamp('trial_started_at')->nullable()->after('bonus_balance');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('is_trial')->default(false)->after('plan');
            $table->timestamp('ending_notified_at')->nullable()->after('expires_at');
            $table->timestamp('ended_notified_at')->nullable()->after('ending_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['is_trial', 'ending_notified_at', 'ended_notified_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('trial_started_at');
        });
    }
};
