<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16);
            $table->string('provider_payment_id')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 8)->default('CNY');
            $table->string('status', 16)->default('pending');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'provider']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

