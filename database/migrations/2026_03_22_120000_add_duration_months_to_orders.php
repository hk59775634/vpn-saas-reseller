<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'duration_months')) {
                $table->unsignedTinyInteger('duration_months')->default(1)->after('region')->comment('购买时长 1~12 个月，与产品周期天数相乘得到 A 站 duration_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'duration_months')) {
                $table->dropColumn('duration_months');
            }
        });
    }
};
