<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Bonusni kartaga yechib olish so'rovlari va admin belgisi */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('bonus_balance');
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            // Karta raqami shifrlangan saqlanadi (Laravel `encrypted` cast)
            $table->text('card_number');
            $table->string('card_holder', 100)->nullable();
            $table->string('status', 20)->default('pending'); // pending | paid | rejected
            $table->string('admin_note', 255)->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
            $table->index(['user_id', 'id']);
        });

        Schema::table('bonus_transactions', function (Blueprint $table) {
            $table->foreignId('withdrawal_id')->nullable()->after('payment_id')
                ->constrained('withdrawals')->nullOnDelete();
            $table->unique(['withdrawal_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('bonus_transactions', function (Blueprint $table) {
            $table->dropUnique(['withdrawal_id', 'type']);
            $table->dropConstrainedForeignId('withdrawal_id');
        });
        Schema::dropIfExists('withdrawals');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
