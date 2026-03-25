@extends('layouts.reseller')

@section('title', '安全与限流')
@section('header_title', '安全与限流')

@section('sidebar')
    @include('reseller.partials.sidebar')
@endsection

@section('content')
<div class="max-w-3xl space-y-6">
    @if (session('message'))
        <div class="console-alert-success mb-6">{{ session('message') }}</div>
    @endif
    @if ($errors->any())
        <div class="console-alert-error mb-6 space-y-1 text-sm">
            @foreach ($errors->all() as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    <div class="console-card p-6">
        <h2 class="mb-2 font-semibold text-slate-900">Redis（自动）</h2>
        @if ($redis_env_configured && $redis_connection_ok)
            <p class="text-sm text-slate-600">已在 <code class="rounded bg-slate-100 px-1 text-xs">.env</code> 中配置 Redis 且连接校验通过；当前进程下缓存 / Session / 队列使用 Redis。</p>
        @elseif ($redis_env_configured && !$redis_connection_ok)
            <p class="text-sm text-amber-800">已填写 <code class="rounded bg-amber-50 px-1 text-xs">REDIS_URL</code> 或 <code class="rounded bg-amber-50 px-1 text-xs">REDIS_HOST</code>，但连接失败，已回退为数据库驱动（请检查 Redis 服务与密码等）。</p>
        @else
            <p class="text-sm text-slate-600">未在 <code class="rounded bg-slate-100 px-1 text-xs">.env</code> 中显式配置 <code class="rounded bg-slate-100 px-1 text-xs">REDIS_URL</code> / <code class="rounded bg-slate-100 px-1 text-xs">REDIS_HOST</code>，不启用 Redis 栈；以 <code class="rounded bg-slate-100 px-1 text-xs">CACHE_STORE</code> 等为准。</p>
        @endif
    </div>

    <form method="POST" action="{{ route('reseller.runtime_settings.update') }}" class="space-y-6">
        @csrf

        <div class="console-card p-6">
            <h2 class="mb-2 font-semibold text-slate-900">公开接口限流（次/分钟，按 IP + 路径）</h2>
            <p class="mb-4 text-xs text-slate-500">用于缓解撞库与刷接口；支付网关可能短时间多次回调，易支付通知可适当放宽。</p>
            <div class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <label class="mb-1 block font-medium text-slate-700">POST /login（用户登录）</label>
                    <input type="number" name="user_login" min="1" max="100000"
                           value="{{ old('user_login', $rate_limits['user_login'] ?? 30) }}"
                           class="console-filter-input w-full max-w-xs">
                    @error('user_login')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block font-medium text-slate-700">POST /register（用户注册）</label>
                    <input type="number" name="user_register" min="1" max="100000"
                           value="{{ old('user_register', $rate_limits['user_register'] ?? 10) }}"
                           class="console-filter-input w-full max-w-xs">
                    @error('user_register')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block font-medium text-slate-700">POST /api/v1/reseller/auth</label>
                    <input type="number" name="reseller_auth" min="1" max="100000"
                           value="{{ old('reseller_auth', $rate_limits['reseller_auth'] ?? 30) }}"
                           class="console-filter-input w-full max-w-xs">
                    @error('reseller_auth')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block font-medium text-slate-700">GET|POST /pay/webhook/epay</label>
                    <input type="number" name="epay_webhook" min="1" max="100000"
                           value="{{ old('epay_webhook', $rate_limits['epay_webhook'] ?? 300) }}"
                           class="console-filter-input w-full max-w-xs">
                    @error('epay_webhook')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="console-btn-primary">保存限流设置</button>
        </div>
    </form>
</div>
@endsection
