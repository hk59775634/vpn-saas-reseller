<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'parent_order_id')) {
                $table->unsignedBigInteger('parent_order_id')
                    ->nullable()
                    ->after('biz_order_no')
                    ->comment('续费单关联的上一笔 B 订单（新购为 null）');
                $table->foreign('parent_order_id')
                    ->references('id')
                    ->on('orders')
                    ->nullOnDelete();
                $table->index('parent_order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'parent_order_id')) {
                $table->dropForeign(['parent_order_id']);
                $table->dropColumn('parent_order_id');
            }
        });
    }
};
