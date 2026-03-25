<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 分销商在 B 站配置的自有产品（基于 A 站产品组合/定价）
        Schema::create('reseller_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reseller_id')->comment('A 站分销商 ID');
            $table->unsignedBigInteger('source_product_id')->comment('A 站产品 ID');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_cents');
            $table->string('currency', 8)->default('CNY');
            $table->unsignedInteger('duration_days')->default(30);
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->index(['reseller_id', 'status']);
        });

        // 用户订单（B 站终端用户购买分销产品）
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reseller_product_id')->constrained('reseller_products')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 8)->default('CNY');
            $table->string('status', 32)->default('pending'); // pending, paid, cancelled
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
        Schema::dropIfExists('reseller_products');
    }
};
