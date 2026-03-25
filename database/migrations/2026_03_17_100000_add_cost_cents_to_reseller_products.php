<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reseller_products', function (Blueprint $table) {
            $table->unsignedBigInteger('cost_cents')->nullable()->after('source_product_id')->comment('A 站成本价（分）');
        });
    }

    public function down(): void
    {
        Schema::table('reseller_products', function (Blueprint $table) {
            $table->dropColumn('cost_cents');
        });
    }
};
