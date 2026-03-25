<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '用户中心') - {{ $siteName }}</title>
    @if(\App\Support\SiteConfig::metaDescription() !== '')
        <meta name="description" content="{{ \App\Support\SiteConfig::metaDescription() }}">
    @endif
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x/dist/cdn.min.js"></script>
    @stack('styles')
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased" style="font-family: 'Plus Jakarta Sans', ui-sans-serif, sans-serif;">
    {{-- 深色顶部导航栏 --}}
    <nav class="console-nav">
        <div class="mx-auto flex w-full max-w-5xl items-center justify-between px-4 sm:px-6">
            <div class="flex min-w-0 flex-col gap-0.5">
                <a href="{{ route('user.home') }}" class="console-nav-brand">{{ $siteName }}</a>
                @if(($siteTagline ?? '') !== '')
                    <span class="truncate text-xs text-slate-400">{{ $siteTagline }}</span>
                @endif
            </div>
            <div class="flex items-center gap-6">
                <a href="{{ route('user.products') }}" class="console-nav-link {{ request()->routeIs('user.products') ? 'active' : '' }}">产品</a>
                @auth
                    <a href="{{ route('user.subscriptions') }}" class="console-nav-link {{ request()->routeIs('user.subscriptions') ? 'active' : '' }}">已购产品</a>
                    <a href="{{ route('user.orders') }}" class="console-nav-link {{ request()->routeIs('user.orders') ? 'active' : '' }}">订单流水</a>
                    <a href="{{ route('user.profile') }}" class="console-nav-link {{ request()->routeIs('user.profile') ? 'active' : '' }}">个人中心</a>
                @endauth
                <a href="{{ route('user.downloads') }}" class="console-nav-link {{ request()->routeIs('user.downloads') ? 'active' : '' }}">下载</a>
                @auth
                    <form method="POST" action="{{ route('user.logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="console-nav-link">退出</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="console-nav-link">登录</a>
                    <a href="{{ route('register') }}" class="console-btn-primary">注册</a>
                @endauth
            </div>
        </div>
    </nav>
    <main class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6">
        @if (session('message'))
            <div class="console-alert-success mb-6">{{ session('message') }}</div>
        @endif
        @if (session('error'))
            <div class="console-alert-error mb-6">{{ session('error') }}</div>
        @endif
        @yield('content')
    </main>
    @if(($siteSupportEmail ?? '') !== '' || ($siteIcp ?? '') !== '')
        <footer class="border-t border-slate-200 bg-white py-6 text-center text-xs text-slate-500">
            @if(($siteSupportEmail ?? '') !== '')
                <p>客服邮箱：<a href="mailto:{{ $siteSupportEmail }}" class="console-link">{{ $siteSupportEmail }}</a></p>
            @endif
            @if(($siteIcp ?? '') !== '')
                <p class="mt-1">{{ $siteIcp }}</p>
            @endif
        </footer>
    @endif
    @stack('scripts')
</body>
</html>
