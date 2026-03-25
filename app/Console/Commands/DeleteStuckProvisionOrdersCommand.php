<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

/**
 * 删除「已支付但未开通」（无 user_vpn_subscription_id）的订单，即订单流水中需「补开通」的死单。
 */
class DeleteStuckProvisionOrdersCommand extends Command
{
    protected $signature = 'orders:delete-stuck-provision {--force : 不询问直接删除}';

    protected $description = '删除状态为已支付、且未关联已购产品（需补开通）的订单';

    public function handle(): int
    {
        $q = Order::query()
            ->where('status', 'paid')
            ->whereNull('user_vpn_subscription_id');

        $count = $q->count();
        if ($count === 0) {
            $this->info('没有需要删除的订单。');

            return self::SUCCESS;
        }

        $this->warn("将删除 {$count} 条订单（已支付、未关联已购产品）。");
        if (!$this->option('force') && !$this->confirm('确认删除？')) {
            $this->info('已取消。');

            return self::SUCCESS;
        }

        $ids = $q->pluck('id')->all();
        $deleted = Order::query()->whereIn('id', $ids)->delete();

        $this->info("已删除 {$deleted} 条订单。");

        return self::SUCCESS;
    }
}
