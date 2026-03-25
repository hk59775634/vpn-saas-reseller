<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'region')) {
                $table->string('region', 64)->nullable()->after('currency')->comment('用户选择的线路/区域，例如 CN-HK');
                $table->index(['region']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'region')) {
                $table->dropIndex(['region']);
                $table->dropColumn('region');
            }
        });
    }
};

