<?php

namespace Database\Seeders;

use App\Models\Reseller;
use App\Models\ResellerApiKey;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * B 站独立库：填充测试分销商与 API Key，便于本地/测试环境登录。
     */
    public function run(): void
    {
        $reseller = Reseller::firstOrCreate(['name' => '测试分销商']);

        ResellerApiKey::firstOrCreate(
            ['api_key' => 'rk_test_reseller_demo'],
            ['reseller_id' => $reseller->id, 'api_key' => 'rk_test_reseller_demo', 'name' => '测试 Key']
        );
    }
}
