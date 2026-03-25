<?php

namespace App\Providers;

use App\Support\RateLimitSettings;
use App\Support\RuntimeStackConfig;
use App\Support\SiteConfig;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            RuntimeStackConfig::apply();
        } catch (\Throwable) {
        }

        $throttleKey = static function (Request $request): string {
            return $request->ip().'|'.$request->path();
        };

        RateLimiter::for('b-user-login', fn (Request $r) => Limit::perMinute(RateLimitSettings::rpm('user_login'))->by($throttleKey($r)));
        RateLimiter::for('b-user-register', fn (Request $r) => Limit::perMinute(RateLimitSettings::rpm('user_register'))->by($throttleKey($r)));
        RateLimiter::for('b-reseller-auth', fn (Request $r) => Limit::perMinute(RateLimitSettings::rpm('reseller_auth'))->by($throttleKey($r)));
        RateLimiter::for('b-epay-webhook', fn (Request $r) => Limit::perMinute(RateLimitSettings::rpm('epay_webhook'))->by($throttleKey($r)));

        View::composer(['layouts.user', 'layouts.reseller', 'layouts.app'], function ($view) {
            $view->with([
                'siteName' => SiteConfig::siteName(),
                'siteTagline' => SiteConfig::tagline(),
                'siteSupportEmail' => SiteConfig::supportEmail(),
                'siteIcp' => SiteConfig::icp(),
            ]);
        });
    }
}
