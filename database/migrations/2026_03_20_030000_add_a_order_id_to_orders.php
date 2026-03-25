<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'a_order_id')) {
                $table->unsignedBigInteger('a_order_id')->nullable()->after('reseller_product_id')->comment('A 站订单ID（用于精准续费绑定）');
                $table->index(['a_order_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'a_order_id')) {
                $table->dropIndex(['a_order_id']);
                $table->dropColumn('a_order_id');
            }
        });
    }
};

