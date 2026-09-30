<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Tarif limitlari: mijoz va mahsulot soni (NULL — cheksiz). Pro — cheksiz. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('max_customers')->nullable()->after('days');
            $table->unsignedInteger('max_products')->nullable()->after('max_customers');
        });

        DB::table('plans')->where('key', 'standard')->update(['max_customers' => 300, 'max_products' => 500]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['max_customers', 'max_products']);
        });
    }
};
