<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bosh sahifadagi reklama karuseli (admin panel orqali boshqariladi)
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('url', 500);
            $table->string('image_path');
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('shop_name');
            // Qarzdor mijozlarga SMS eslatma yuborish (do'kon egasi o'chirib qo'yishi mumkin)
            $table->boolean('sms_reminders')->default(true)->after('locale');
        });

        Schema::table('debts', function (Blueprint $table) {
            $table->date('due_soon_sms_at')->nullable();
            $table->date('overdue_sms_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('debts', fn (Blueprint $table) => $table->dropColumn(['due_soon_sms_at', 'overdue_sms_at']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['avatar_path', 'sms_reminders']));
        Schema::dropIfExists('banners');
    }
};
