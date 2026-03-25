<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '分销商中心') - {{ $siteName ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x/dist/cdn.min.js"></script>
    @stack('styles')
</head>
<body class="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-800 antialiased" style="font-family: 'Plus Jakarta Sans', ui-sans-serif, sans-serif;"
      x-data="{ resellerName: localStorage.getItem('reseller_name') || '', logout() { localStorage.removeItem('reseller_token'); localStorage.removeItem('reseller_name'); location.replace('/reseller/login'); } }"
      x-init="
        var p = window.location.pathname.replace(/\/$/, '');
        if (!localStorage.getItem('reseller_token') && p !== '/reseller/login') {
          location.replace('/reseller/login');
        }
        resellerName = localStorage.getItem('reseller_name') || '';
      ">
    {{-- 深色顶部导航栏 --}}
    <header class="console-nav h-14 shrink-0">
        <div class="flex w-full items-center justify-between px-4">
            <span class="console-nav-brand">{{ $siteName ?? config('app.name') }} · 分销商</span>
            <span class="text-xs text-slate-400" x-text="resellerName || ''"></span>
        </div>
    </header>
    <div class="flex min-h-0 flex-1">
        {{-- 左侧边栏导航 --}}
        <aside class="console-sidebar">
            <div class="console-sidebar-header">
                <a href="{{ url('/reseller') }}" class="console-nav-brand block text-white">{{ $siteName ?? config('app.name') }}</a>
                <p class="mt-0.5 text-xs text-slate-400">分销商 · B站</p>
            </div>
            <nav class="console-sidebar-nav">
                @yield('sidebar')
            </nav>
            <div class="border-t border-slate-700/50 p-3 space-y-1">
                <p class="truncate px-3 py-1 text-xs text-slate-500" x-text="resellerName ? '当前：' + resellerName : ''"></p>
                <a href="#" @click.prevent="logout()" class="console-sidebar-link block">退出</a>
            </div>
        </aside>
        {{-- 主内容区 --}}
        <div class="console-main">
            <header class="console-header">
                <h1 class="text-lg font-semibold text-slate-900">@yield('header_title', '分销商中心')</h1>
            </header>
            <div class="console-content">
                @yield('content')
            </div>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
