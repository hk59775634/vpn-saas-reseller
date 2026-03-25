@extends('layouts.reseller')

@section('title', '支付设置')
@section('header_title', '支付设置')

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

    <form method="POST" action="{{ route('reseller.payment.update') }}" class="space-y-6">
        @csrf

        <div class="console-card p-6">
            <h2 class="mb-4 font-semibold text-slate-900">彩虹易支付</h2>
            <p class="mb-4 text-xs text-slate-500">与 A 站管理后台一致：入库优先，未填项回退 <code class="rounded bg-slate-100 px-1">.env</code>（<code class="rounded bg-slate-100 px-1">EPAY_*</code>）。密钥加密存储。</p>
            <div class="mb-4 flex items-center gap-3 text-sm">
                <label class="text-slate-700">启用易支付</label>
                <select name="epay_enabled" class="console-filter-input">
                    <option value="1" @selected(old('epay_enabled', \App\Support\PaymentConfig::enabled() ? '1' : '0') === '1')>是</option>
                    <option value="0" @selected(old('epay_enabled', \App\Support\PaymentConfig::enabled() ? '1' : '0') === '0')>否</option>
                </select>
            </div>
            <div class="mb-4 flex items-center gap-3 text-sm">
                <label class="text-slate-700">允许模拟支付</label>
                <select name="epay_allow_simulated_payment" class="console-filter-input">
                    <option value="1" @selected(old('epay_allow_simulated_payment', \App\Support\PaymentConfig::allowSimulatedPayment() ? '1' : '0') === '1')>是</option>
                    <option value="0" @selected(old('epay_allow_simulated_payment', \App\Support\PaymentConfig::allowSimulatedPayment() ? '1' : '0') === '0')>否</option>
                </select>
            </div>
            <div class="space-y-3 text-sm">
                <div>
                    <label class="mb-1 block text-slate-600">API 地址（网关，不含 submit.php）</label>
                    <input type="url" name="epay_gateway" value="{{ old('epay_gateway', \App\Support\PaymentConfig::gateway()) }}"
                           class="console-filter-input w-full" placeholder="https://pay.example.com">
                    @error('epay_gateway')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-slate-600">商户 ID（PID）</label>
                    <input type="text" name="epay_pid" value="{{ old('epay_pid', \App\Support\PaymentConfig::pid()) }}"
                           class="console-filter-input w-full" autocomplete="off">
                    @error('epay_pid')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-slate-600">MD5 密钥</label>
                    <input type="password" name="epay_key" value=""
                           class="console-filter-input w-full" placeholder="留空则不修改已保存的密钥" autocomplete="new-password">
                    @if(!empty($epay_key_set) && ($epay_key_hint !== ''))
                        <p class="mt-1 text-xs text-slate-500">当前已配置：{{ $epay_key_hint }}</p>
                    @endif
                    @error('epay_key')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-slate-600">异步通知 URL（留空则用默认）</label>
                    <input type="url" name="epay_notify_url" value="{{ old('epay_notify_url', $epay_notify_url_raw) }}"
                           class="console-filter-input w-full" placeholder="">
                    <p class="mt-1 text-xs text-slate-500">当前生效：<span class="break-all font-mono">{{ $epay_notify_url_effective }}</span> — 请在易支付商户后台填此地址</p>
                    @error('epay_notify_url')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-slate-600">同步跳转 URL（留空则用默认）</label>
                    <input type="url" name="epay_return_url" value="{{ old('epay_return_url', $epay_return_url_raw) }}"
                           class="console-filter-input w-full" placeholder="">
                    <p class="mt-1 text-xs text-slate-500">当前生效：<span class="break-all font-mono">{{ $epay_return_url_effective }}</span></p>
                    @error('epay_return_url')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <button type="submit" class="console-btn-primary">保存配置</button>
    </form>
</div>
@endsection

