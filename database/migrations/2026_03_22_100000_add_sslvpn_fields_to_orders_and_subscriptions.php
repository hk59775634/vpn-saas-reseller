<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'sslvpn_username')) {
                $table->string('sslvpn_username', 64)->nullable()->after('region')->comment('SSL VPN 登录名用户填写前缀，完整为 xxx@分销商ID（A 站写入 radcheck）');
            }
            if (!Schema::hasColumn('orders', 'sslvpn_password')) {
                $table->text('sslvpn_password')->nullable()->after('sslvpn_username')->comment('加密存储');
            }
        });

        Schema::table('user_vpn_subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('user_vpn_subscriptions', 'radius_login')) {
                $table->string('radius_login', 191)->nullable()->after('status')->comment('A 站完整 RADIUS 登录名');
            }
            if (!Schema::hasColumn('user_vpn_subscriptions', 'sslvpn_password')) {
                $table->text('sslvpn_password')->nullable()->after('radius_login')->comment('与订单同步，加密');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'sslvpn_password')) {
                $table->dropColumn('sslvpn_password');
            }
            if (Schema::hasColumn('orders', 'sslvpn_username')) {
                $table->dropColumn('sslvpn_username');
            }
        });

        Schema::table('user_vpn_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('user_vpn_subscriptions', 'sslvpn_password')) {
                $table->dropColumn('sslvpn_password');
            }
            if (Schema::hasColumn('user_vpn_subscriptions', 'radius_login')) {
                $table->dropColumn('radius_login');
            }
        });
    }
};
