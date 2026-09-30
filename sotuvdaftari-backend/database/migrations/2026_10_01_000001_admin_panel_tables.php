<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Admin panel: bazadagi tariflar, foydalanuvchini bloklash, admin paroli, e'lonlar (bildirishnomalar) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key', 10)->unique(); // standard | pro
            $table->unsignedInteger('price');    // so'm
            $table->unsignedSmallInteger('days');
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort')->default(0);
            $table->json('features')->nullable();
            $table->timestamps();
        });

        // Standart qiymatlar (.env dagi STANDARD_PRICE/PRO_PRICE... dan olinadi); keyin admin panelda o'zgartiriladi
        $now = now();
        DB::table('plans')->insert([
            [
                'key' => 'standard',
                'price' => (int) config('savdodaftar.billing.plans.standard.price', 12000),
                'days' => (int) config('savdodaftar.billing.plans.standard.days', 30),
                'is_active' => true,
                'sort' => 1,
                'features' => json_encode(['sales', 'inventory']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'pro',
                'price' => (int) config('savdodaftar.billing.plans.pro.price', 49000),
                'days' => (int) config('savdodaftar.billing.plans.pro.days', 30),
                'is_active' => true,
                'sort' => 2,
                'features' => json_encode(['sales', 'inventory', 'voice', 'ai_assistant', 'ocr_import', 'advanced_reports']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('is_admin');
            $table->string('block_reason', 255)->nullable()->after('blocked_at');
            $table->string('admin_password')->nullable()->after('block_reason');
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('body');
            $table->string('audience', 10)->default('all'); // all | user | plan
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('plan', 10)->nullable(); // free | standard | pro
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('id');
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();

            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcements');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['blocked_at', 'block_reason', 'admin_password']);
        });
        Schema::dropIfExists('plans');
    }
};
