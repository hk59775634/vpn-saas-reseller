<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * duration_months 在「每周期 1 天」时表示续费天数，可能达到 365，需超过 tinyint(255)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'duration_months')) {
                $table->unsignedSmallInteger('duration_months')->default(1)->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'duration_months')) {
                $table->unsignedTinyInteger('duration_months')->default(1)->change();
            }
        });
    }
};
