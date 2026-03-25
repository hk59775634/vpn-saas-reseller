<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 清空 B 站：支付流水、订单、用户已购订阅（user_vpn_subscriptions）。
 * 不影响 users、分销商、分销产品等基础数据。
 */
class PurgePurchasesDataCommand extends Command
{
    protected $signature = 'vpn:purge-purchases {--force : 不询问直接执行}';

    protected $description = '清空已购产品、订单与支付流水（payments / orders / user_vpn_subscriptions）';

    public function handle(): int
    {
        if (!$this->option('force')) {
            if (!$this->confirm('将删除全部 payments、orders、user_vpn_subscriptions，是否继续？')) {
                $this->info('已取消。');
                return self::SUCCESS;
            }
        }

        $driver = DB::getDriverName();
        $disableFk = $driver === 'mysql' || $driver === 'mariadb';

        DB::transaction(function () use ($disableFk): void {
            if ($disableFk) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }
            try {
                if (DB::getSchemaBuilder()->hasTable('payments')) {
                    DB::table('payments')->delete();
                }
                if (DB::getSchemaBuilder()->hasTable('orders')) {
                    DB::table('orders')->delete();
                }
                if (DB::getSchemaBuilder()->hasTable('user_vpn_subscriptions')) {
                    DB::table('user_vpn_subscriptions')->delete();
                }
            } finally {
                if ($disableFk) {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                }
            }
        });

        $this->info('B 站已购与订单数据已清空。');

        return self::SUCCESS;
    }
}
