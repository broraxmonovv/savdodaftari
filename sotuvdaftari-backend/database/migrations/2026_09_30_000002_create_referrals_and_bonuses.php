<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Referal dasturi: kod, taklif qilgan foydalanuvchi, bonus balansi va bonus tranzaksiyalari */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 12)->nullable()->unique()->after('phone');
            $table->foreignId('referred_by_id')->nullable()->after('referral_code')
                ->constrained('users')->nullOnDelete();
            $table->decimal('bonus_balance', 14, 2)->default(0)->after('referred_by_id');
        });

        Schema::create('bonus_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Bonus kim to'lovidan hosil bo'ldi
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // referral | reversal
            $table->decimal('amount', 14, 2); // reversal uchun manfiy
            $table->timestamps();

            $table->unique(['payment_id', 'type']);
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_transactions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_id');
            $table->dropColumn(['referral_code', 'bonus_balance']);
        });
    }
};
