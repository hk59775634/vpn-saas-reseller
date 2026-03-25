<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ResellerProduct;
use App\Models\UserVpnSubscription;
use App\Services\VpnAValidateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private VpnAValidateService $vpnA
    ) {}

    /**
     * 用户前台产品列表（仅展示本站点所属分销商的产品）
     */
    public function index(): View
    {
        // 与 SSL 后缀一致：优先使用 GET /api/v1/reseller/me 的 id；失败时回退 VPN_A_RESELLER_ID
        $resellerId = $this->vpnA->fetchResellerIdFromMe() ?? (int) config('services.vpn_a.reseller_id', 1);
        $products = ResellerProduct::where('reseller_id', $resellerId)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();
        $publicById = collect($this->vpnA->getPublicProducts())->keyBy('id');
        $products = $products->map(function (ResellerProduct $p) use ($publicById) {
            $src = $publicById->get($p->source_product_id);
            $p->enable_radius = VpnAValidateService::flagEnabled($src['enable_radius'] ?? null, true);
            $p->enable_wireguard = VpnAValidateService::flagEnabled($src['enable_wireguard'] ?? null, true);

            return $p;
        });
        $regions = $this->vpnA->getPublicRegions();

        $renewSubscriptionIdByProductId = [];
        if (Auth::check()) {
            $renewSubscriptionIdByProductId = UserVpnSubscription::query()
                ->where('user_id', Auth::id())
                ->whereIn('reseller_product_id', $products->pluck('id'))
                ->get()
                ->keyBy('reseller_product_id')
                ->map(fn (UserVpnSubscription $s) => $s->id)
                ->all();
        }

        return view('user.products', [
            'products' => $products,
            'regions' => $regions,
            'defaultRegion' => (string) (config('services.vpn_a.default_region') ?? ''),
            'renewSubscriptionIdByProductId' => $renewSubscriptionIdByProductId,
        ]);
    }
}
