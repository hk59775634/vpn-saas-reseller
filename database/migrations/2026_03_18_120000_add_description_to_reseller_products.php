<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reseller_products', function (Blueprint $table) {
            if (!Schema::hasColumn('reseller_products', 'description')) {
                $table->text('description')->nullable()->after('name')->comment('B 站产品描述，支持 Markdown/HTML');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reseller_products', function (Blueprint $table) {
            if (Schema::hasColumn('reseller_products', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};

