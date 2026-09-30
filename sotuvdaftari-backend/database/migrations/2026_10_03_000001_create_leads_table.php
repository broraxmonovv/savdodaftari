<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Veb saytdan kelgan arizalar (potensial foydalanuvchilar) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('phone', 20)->index();
            $table->string('business_type', 50)->nullable();
            $table->text('message')->nullable();
            $table->string('source', 20)->default('site');
            $table->string('status', 20)->default('new'); // new | contacted | done | spam
            $table->string('admin_note', 255)->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
