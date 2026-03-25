<?php

use App\Http\Controllers\Reseller\ResellerViewController;
use App\Http\Controllers\Reseller\AdminAuthController;
use App\Http\Controllers\User\AuthController as UserAuthController;
use App\Http\Controllers\User\DownloadController;
use App\Http\Controllers\User\PaymentController;
use App\Http\Controllers\User\OrderController;
use App\Http\Controllers\User\VpnSubscriptionController;
use App\Http\Controllers\User\ProfileController;
use App\Http\Controllers\User\ProductController;
use App\Support\UserLanding;
use Illuminate\Support\Facades\Route;

// 用户前台：首页按是否已有已购产品分流（与登录/注册后默认落地一致）
Route::get('/', fn () => redirect()->route(UserLanding::routeName()))->name('user.home');
Route::get('/products', [ProductController::class, 'index'])->name('user.products');

Route::get('/login', [UserAuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [UserAuthController::class, 'login'])->middleware(['throttle:b-user-login', 'guest']);
Route::get('/register', [UserAuthController::class, 'showRegister'])->name('register')->middleware('guest');
Route::post('/register', [UserAuthController::class, 'register'])->middleware(['throttle:b-user-register', 'guest']);
Route::post('/logout', [UserAuthController::class, 'logout'])->name('user.logout')->middleware('auth');

Route::get('/orders', [OrderController::class, 'index'])->name('user.orders')->middleware('auth');
Route::get('/orders/confirm/{reseller_product}', [OrderController::class, 'showConfirm'])->name('user.orders.confirm')->middleware('auth');
Route::post('/orders', [OrderController::class, 'store'])->name('user.orders.store')->middleware('auth');
Route::post('/orders/{id}/pay', [OrderController::class, 'pay'])->name('user.orders.pay')->middleware('auth');
Route::match(['get', 'post'], '/orders/{id}/pay/epay', [OrderController::class, 'payEpay'])->name('user.orders.pay.epay')->middleware('auth');
Route::post('/orders/{id}/retry-provision', [OrderController::class, 'retryProvision'])->name('user.orders.retry_provision')->middleware('auth');

Route::get('/subscriptions', [VpnSubscriptionController::class, 'index'])->name('user.subscriptions')->middleware('auth');
Route::get('/subscriptions/{id}/detail-data', [VpnSubscriptionController::class, 'detailJson'])->name('user.subscriptions.detail_data')->middleware('auth');
Route::get('/subscriptions/{subscriptionId}/renew', [VpnSubscriptionController::class, 'showRenewForm'])->name('user.subscriptions.renew.show')->middleware('auth');
Route::post('/subscriptions/{subscriptionId}/renew', [VpnSubscriptionController::class, 'renew'])->name('user.subscriptions.renew')->middleware('auth');

Route::get('/profile', [ProfileController::class, 'show'])->name('user.profile')->middleware('auth');
Route::put('/profile', [ProfileController::class, 'update'])->name('user.profile.update')->middleware('auth');
Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('user.profile.password')->middleware('auth');

Route::get('/downloads', [DownloadController::class, 'index'])->name('user.downloads');

// 支付相关（用户前台）
Route::middleware('auth')->group(function () {
    Route::get('/api/v1/site/vpn-identity', [OrderController::class, 'vpnIdentity'])->name('user.api.vpn_identity');
    Route::post('/subscriptions/{subscriptionId}/sync-sslvpn', [VpnSubscriptionController::class, 'syncSslVpn'])->name('user.subscriptions.sync_ssl_vpn');
    Route::post('/api/v1/payments', [PaymentController::class, 'store'])->name('user.payments.store');
    Route::get('/api/v1/payments/{payment}', [PaymentController::class, 'show'])->name('user.payments.show');
});

// 彩虹易支付异步通知（商户后台填此地址；易支付可能 GET/POST）
Route::match(['get', 'post'], '/pay/webhook/epay', [PaymentController::class, 'webhookEpay'])->middleware('throttle:b-epay-webhook')->name('pay.webhook.epay');

// 分销商后台
Route::prefix('reseller')->group(function () {
    Route::get('/login', [ResellerViewController::class, 'login'])->name('reseller.login');
    Route::get('/login/', [ResellerViewController::class, 'login']);

    // 后台各页面（目前仍依赖前端 token 校验，后续可引入服务端 session 中间件）
    Route::get('/', [ResellerViewController::class, 'dashboard'])->name('reseller.dashboard');
    Route::get('/products', [ResellerViewController::class, 'products'])->name('reseller.products');
    Route::get('/users', [ResellerViewController::class, 'users'])->name('reseller.users');
    Route::get('/orders', [ResellerViewController::class, 'orders'])->name('reseller.orders');
    Route::get('/subscriptions', [ResellerViewController::class, 'subscriptions'])->name('reseller.subscriptions');
    Route::get('/api-keys', [ResellerViewController::class, 'apiKeys'])->name('reseller.api_keys');
    Route::post('/api-keys', [ResellerViewController::class, 'updateApiKeys'])->name('reseller.api_keys.update');
    Route::get('/payment', [ResellerViewController::class, 'payment'])->name('reseller.payment');
    Route::post('/payment', [ResellerViewController::class, 'updatePayment'])->name('reseller.payment.update');
    Route::get('/site-settings', [ResellerViewController::class, 'siteSettings'])->name('reseller.site_settings');
    Route::post('/site-settings', [ResellerViewController::class, 'updateSiteSettings'])->name('reseller.site_settings.update');
    Route::get('/runtime-settings', [ResellerViewController::class, 'runtimeSettings'])->name('reseller.runtime_settings');
    Route::post('/runtime-settings', [ResellerViewController::class, 'updateRuntimeSettings'])->name('reseller.runtime_settings.update');
});
