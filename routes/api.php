<?php

use App\Http\Controllers\Api\ResellerApiController;
use App\Http\Controllers\Api\ResellerAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| B 站分销商 API（API Key 登录，Token 为 API Key 本身）
|--------------------------------------------------------------------------
*/

Route::get('/health', fn () => response()->json(['status' => 'ok']));

// 公开：API Key 登录（按 IP + 路径限流）
Route::post('/v1/reseller/auth', [ResellerAuthController::class, 'login'])->middleware('throttle:b-reseller-auth');

// 以下接口需携带 Authorization: Bearer <api_key>
Route::middleware('reseller')->group(function (): void {
    $api = ResellerApiController::class;
    Route::get('/v1/reseller/me', [$api, 'me']);
    Route::get('/v1/reseller/stats', [$api, 'stats']);
    Route::get('/v1/reseller/me/api_keys', [$api, 'apiKeys']);
    Route::get('/v1/reseller/a_products', [$api, 'aProducts']);
    Route::get('/v1/reseller/products_merged', [$api, 'productsMerged']);
    Route::get('/v1/reseller/products', [$api, 'products']);
    Route::post('/v1/reseller/products', [$api, 'storeProduct']);
    Route::put('/v1/reseller/products/{id}', [$api, 'updateProduct']);
    Route::delete('/v1/reseller/products/{id}', [$api, 'destroyProduct']);
    Route::get('/v1/reseller/users', [$api, 'users']);
    Route::put('/v1/reseller/users/{id}', [$api, 'updateUser']);
    Route::delete('/v1/reseller/users/{id}', [$api, 'destroyUser']);
    Route::get('/v1/reseller/orders', [$api, 'orders']);
    Route::get('/v1/reseller/subscriptions', [$api, 'subscriptions']);
    Route::get('/v1/reseller/subscriptions/{id}', [$api, 'subscriptionShow']);
    Route::post('/v1/reseller/subscriptions/{id}/sync-sslvpn', [$api, 'syncSslVpnForSubscription']);
});
