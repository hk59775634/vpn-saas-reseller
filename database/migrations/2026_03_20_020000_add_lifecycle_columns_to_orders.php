<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'activated_at')) {
                $table->timestamp('activated_at')->nullable()->after('paid_at')->comment('开通时间');
            }
            if (!Schema::hasColumn('orders', 'last_renewed_at')) {
                $table->timestamp('last_renewed_at')->nullable()->after('activated_at')->comment('最后续费时间');
            }
            if (!Schema::hasColumn('orders', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('last_renewed_at')->comment('到期时间（同步自 A 站）');
            }
        });

        // 历史回填：已支付订单将开通时间回填为支付时间（或创建时间）
        DB::table('orders')
            ->where('status', 'paid')
            ->whereNull('activated_at')
            ->update(['activated_at' => DB::raw('COALESCE(paid_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
            if (Schema::hasColumn('orders', 'last_renewed_at')) {
                $table->dropColumn('last_renewed_at');
            }
            if (Schema::hasColumn('orders', 'activated_at')) {
                $table->dropColumn('activated_at');
            }
        });
    }
};

