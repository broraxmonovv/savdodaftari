<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tariflar: to'lov qaysi tarif (standard | pro) uchun ekanligini saqlaydi. Avvalgi to'lovlar — Pro. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('plan', 10)->default('pro')->after('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('plan');
        });
    }
};
