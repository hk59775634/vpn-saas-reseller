@extends('layouts.user')

@section('title', '确认订单')

@section('content')
<section class="page-section">
    <nav class="mb-4 text-sm text-slate-500">
        <a href="{{ route('user.products') }}" class="console-link">产品</a>
        <span class="mx-1">/</span>
        <span class="text-slate-800">确认订单</span>
    </nav>
    <h1 class="page-title">确认订单</h1>
    @php
        $unitDays = max(1, (int) $product->duration_days);
        $isDailyBilling = $unitDays === 1;
    @endphp
    <p class="page-desc">
        单价按「每 {{ $unitDays }} 天」为一计费周期；购买时长请按下方单位选择（@if($isDailyBilling)按<strong>天</strong>@else按<strong>周期</strong>，每周期 {{ $unitDays }} 天@endif）。可选线路；含 SSL VPN 时填写账号密码。确认后创建订单并支付。
    </p>
</section>

@php
    $orderConfirmAlpine = [
        'daily' => $isDailyBilling,
        'days' => (int) old('duration_months', 1),
        'periods' => (int) old('duration_months', 1),
        'unitCents' => (int) $product->price_cents,
        'ddays' => $unitDays,
        'sslUser' => (string) old('sslvpn_username', ''),
        'sslSuffix' => (string) data_get($vpn_identity, 'sslvpn_username_suffix', ''),
    ];
@endphp
<div
    class="console-card p-6 sm:p-8"
    {{-- 使用 @json + 单引号属性，避免手写 x-data 对象被 Blade/引号破坏；勿用 {{ json_encode }} 会 e() 成 &quot; 导致 Alpine 无法解析 --}}
    x-data='@json($orderConfirmAlpine)'
>
    <div class="border-b border-slate-100 pb-4">
        <h2 class="text-lg font-semibold text-slate-900">{{ $product->name }}</h2>
        @if($product->description)
            <div class="mt-2 prose prose-sm max-w-none text-slate-600">
                {!! \Illuminate\Support\Str::markdown($product->description) !!}
            </div>
        @endif
        <p class="mt-4 text-sm text-slate-600">
            单价 <span class="font-semibold text-sky-600">{{ number_format($product->price_cents / 100, 2) }}</span> 元
            / 每 {{ $unitDays }} 天（一个计费周期）
        </p>
        <p class="mt-2 text-2xl font-semibold text-sky-600">
            应付合计：
            <span x-text="daily ? (days * unitCents / 100).toFixed(2) : (periods * unitCents / 100).toFixed(2)"></span> 元
            <span class="text-base font-normal text-slate-500" x-show="daily">（<span x-text="days"></span> 天）</span>
            <span class="text-base font-normal text-slate-500" x-show="!daily">（<span x-text="periods"></span> 个周期，共 <span x-text="periods * ddays"></span> 天）</span>
        </p>
    </div>

    <form method="POST" action="{{ route('user.orders.store') }}" class="mt-6 space-y-6">
        @csrf
        <input type="hidden" name="reseller_product_id" value="{{ $product->id }}">

        <div>
            @if($isDailyBilling)
                <label class="block text-sm font-medium text-slate-700" for="duration_months_daily">购买时长（天）</label>
                <input
                    type="number"
                    id="duration_months_daily"
                    name="duration_months"
                    min="1"
                    max="365"
                    x-model.number="days"
                    required
                    class="console-input mt-1 max-w-xs"
                >
                <p class="mt-1 text-xs text-slate-500">按天计费：填写 1～365 天；总价 = 天数 × 单价（单价为每 1 天的价格）。</p>
            @else
                <label class="block text-sm font-medium text-slate-700" for="duration_months_periods">购买时长（周期）</label>
                <select
                    id="duration_months_periods"
                    name="duration_months"
                    x-model.number="periods"
                    class="console-input mt-1 max-w-md"
                    required
                >
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((int) old('duration_months', 1) === $m)>
                            {{ $m }} 个周期（{{ $m * $unitDays }} 天）
                        </option>
                    @endfor
                </select>
                <p class="mt-1 text-xs text-slate-500">每周期 {{ $unitDays }} 天；可选 1～12 个周期。总价 = 周期数 × 单价。非按「自然月」计算。</p>
            @endif
            @error('duration_months')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        @if(!empty($regions) && is_array($regions))
            <div>
                <label class="block text-sm font-medium text-slate-700" for="confirmRegion">接入线路</label>
                <select id="confirmRegion" name="region" class="console-input mt-1 max-w-md">
                    <option value="">（可选）</option>
                    @foreach($regions as $r)
                        <option value="{{ $r }}" @selected(($defaultRegion ?? '') === $r)>{{ $r }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">用于开通时绑定区域；WireGuard 产品需选择有效线路。</p>
            </div>
        @endif

        @if($enable_wireguard && !$enable_radius)
            <div class="rounded-lg border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-slate-600">
                本产品仅含 WireGuard，支付开通后可在「已购产品」查看配置，<strong>无需</strong>填写 VPN 账号密码。
            </div>
        @endif

        @if($enable_radius)
            <div class="rounded-lg border border-amber-200 bg-amber-50/60 px-4 py-4">
                <p class="text-sm font-medium text-amber-900">SSL VPN 专用账号</p>
                @if($vpn_identity && !empty($vpn_identity['ok']))
                    <p class="mt-1 text-xs text-amber-800/90">
                        分销商 ID（SSL 登录名 <span class="font-mono">@</span> 后缀）：<span class="font-mono font-semibold">{{ $vpn_identity['reseller_id'] }}</span>
                        <span class="text-amber-700/80">（来源：A 站 <code class="rounded bg-amber-100/80 px-0.5 text-[10px]">GET /api/v1/reseller/me</code>，Bearer <code class="rounded bg-amber-100/80 px-0.5 text-[10px]">VPN_A_RESELLER_API_KEY</code>）</span>
                    </p>
                @endif
                <p class="mt-1 text-xs text-amber-800/90">完整登录名为「您填写的用户名@分销商ID」，与 A 站 FreeRADIUS 一致。请牢记密码用于客户端连接。</p>
                <label class="mt-3 block text-xs font-medium text-amber-900">用户名（仅前缀，不含 @）</label>
                <input type="text" name="sslvpn_username" required autocomplete="username"
                       pattern="[a-zA-Z0-9._\-]+" maxlength="64"
                       x-model="sslUser"
                       class="console-input mt-1 w-full max-w-md text-sm" placeholder="仅字母、数字及 ._-">
                <p class="mt-2 rounded border border-amber-200/80 bg-white/70 px-3 py-2 text-xs text-amber-950">
                    <span class="text-amber-800/90">完整 SSL 登录名预览：</span>
                    <span class="font-mono font-semibold" x-text="(sslUser || '').trim() ? (sslUser.trim() + '@' + sslSuffix) : '（请先输入用户名）'"></span>
                </p>
                @error('sslvpn_username')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror

                <label class="mt-3 block text-xs font-medium text-amber-900">密码</label>
                <input type="password" name="sslvpn_password" required autocomplete="new-password" minlength="8"
                       class="console-input mt-1 w-full max-w-md text-sm" placeholder="至少 8 位">
                @error('sslvpn_password')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @endif

        <div class="flex flex-wrap gap-3 pt-2">
            <a href="{{ route('user.products') }}" class="console-btn-secondary">返回产品</a>
            <button type="submit" class="console-btn-primary">确认并创建订单</button>
        </div>
    </form>
</div>
@endsection
