<?php

namespace App\Support;

use App\Models\UserVpnSubscription;
use Illuminate\Support\Facades\Auth;

/**
 * 用户前台默认落地页：有已购产品 → 已购产品；否则 → 产品列表。未登录 → 产品列表。
 */
class UserLanding
{
    public static function routeName(): string
    {
        $user = Auth::user();
        if ($user === null) {
            return 'user.products';
        }

        return UserVpnSubscription::query()
            ->where('user_id', $user->id)
            ->exists()
            ? 'user.subscriptions'
            : 'user.products';
    }
}
