<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 订单 = 收入流水；user_vpn_subscriptions = 用户已购 VPN 产品（与 A 站订阅一一对应）
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_vpn_subscriptions')) {
            Schema::create('user_vpn_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('reseller_product_id')->constrained('reseller_products')->cascadeOnDelete();
                $table->unsignedBigInteger('a_order_id')->nullable()->index()->comment('A 站订阅订单主键（续费 target）');
                $table->string('region', 64)->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('last_renewed_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->string('status', 32)->default('active')->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('orders')) {
            try {
                Schema::table('orders', function (Blueprint $table) {
                    if (Schema::hasColumn('orders', 'parent_order_id')) {
                        $table->dropForeign(['parent_order_id']);
                    }
                });
            } catch (\Throwable) {
                // SQLite 等环境可能无命名外键
            }

            if (!Schema::hasColumn('orders', 'user_vpn_subscription_id')) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->foreignId('user_vpn_subscription_id')
                        ->nullable()
                        ->after('biz_order_no')
                        ->constrained('user_vpn_subscriptions')
                        ->nullOnDelete();
                });
            }

            // SQLite：必须先删索引再删列，否则会报 no such column
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'a_order_id')) {
                    try {
                        $table->dropIndex(['a_order_id']);
                    } catch (\Throwable) {
                    }
                }
                if (Schema::hasColumn('orders', 'parent_order_id')) {
                    try {
                        $table->dropIndex(['parent_order_id']);
                    } catch (\Throwable) {
                    }
                }
            });

            $dropCols = [];
            foreach (['a_order_id', 'parent_order_id', 'activated_at', 'last_renewed_at', 'expires_at'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $dropCols[] = $col;
                }
            }
            if ($dropCols !== []) {
                Schema::table('orders', function (Blueprint $table) use ($dropCols) {
                    $table->dropColumn($dropCols);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'user_vpn_subscription_id')) {
                $table->dropForeign(['user_vpn_subscription_id']);
                $table->dropColumn('user_vpn_subscription_id');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'a_order_id')) {
                $table->unsignedBigInteger('a_order_id')->nullable()->after('reseller_product_id');
            }
            if (!Schema::hasColumn('orders', 'parent_order_id')) {
                $table->unsignedBigInteger('parent_order_id')->nullable()->after('biz_order_no');
            }
            if (!Schema::hasColumn('orders', 'activated_at')) {
                $table->timestamp('activated_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'last_renewed_at')) {
                $table->timestamp('last_renewed_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'expires_at')) {
                $table->timestamp('expires_at')->nullable();
            }
        });

        Schema::dropIfExists('user_vpn_subscriptions');
    }
};
