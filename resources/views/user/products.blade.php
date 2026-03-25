@extends('layouts.user')

@section('title', '产品')

@section('content')
<section class="page-section">
    <h1 class="page-title">套餐产品</h1>
    <p class="page-desc">选择适合您的套餐；未购买过该产品请点击「立即购买」，已有订阅请点击「立即续费」。</p>

    @auth
        @if(!empty($regions) && is_array($regions))
            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                <label class="text-sm font-medium text-slate-700" for="regionSelect">默认线路偏好（可选）</label>
                <select id="regionSelect" class="console-input sm:max-w-xs">
                    @foreach($regions as $r)
                        <option value="{{ $r }}" @if(($defaultRegion ?? '') === $r) selected @endif>{{ $r }}</option>
                    @endforeach
                </select>
                <span class="text-xs text-slate-500">将带入确认页，可在确认页修改。</span>
            </div>
        @endif
    @endauth
</section>
@if($products->isEmpty())
    <div class="console-card p-8 text-center">
        <p class="text-slate-500">暂无在售产品。</p>
    </div>
@else
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($products as $p)
        <div class="console-card flex flex-col p-6 shadow-sm transition hover:border-slate-300 hover:shadow-md">
            <h3 class="font-semibold text-slate-900">{{ $p->name }}</h3>

            @if($p->description)
                <div class="mt-3 rounded-md bg-slate-50/80 border border-slate-200 px-3 py-2">
                    <div class="prose prose-sm max-w-none text-slate-700">
                        {!! \Illuminate\Support\Str::markdown($p->description) !!}
                    </div>
                </div>
            @endif

            <p class="mt-4 text-xl font-semibold text-sky-600">
                {{ number_format($p->price_cents / 100, 2) }} 元
                <span class="text-sm font-normal text-slate-500">/ {{ $p->duration_days }} 天</span>
            </p>

            @if(!($p->enable_radius ?? true) && ($p->enable_wireguard ?? true))
                <p class="mt-3 text-xs text-slate-500">仅 WireGuard：确认页无需填写账号密码。</p>
            @elseif(($p->enable_radius ?? true))
                <p class="mt-3 text-xs text-slate-500">含 SSL VPN：确认页填写专用账号密码。</p>
            @endif

            <div class="mt-6 flex flex-1 items-end">
                @auth
                    @php $renewSubId = $renewSubscriptionIdByProductId[$p->id] ?? null; @endphp
                    @if($renewSubId)
                        <a href="{{ route('user.subscriptions.renew.show', $renewSubId) }}"
                           class="console-btn-primary w-full text-center">
                            立即续费
                        </a>
                    @else
                        <a href="{{ route('user.orders.confirm', $p->id) }}"
                           class="js-buy-to-confirm console-btn-primary w-full text-center">
                            立即购买
                        </a>
                    @endif
                @else
                    <p class="text-sm text-slate-500"><a href="{{ route('login') }}" class="console-link font-medium">登录</a> 后购买</p>
                @endauth
            </div>
        </div>
        @endforeach
    </div>
@endif

@auth
    @if(!empty($regions) && is_array($regions))
        <script>
            (function () {
                const sel = document.getElementById('regionSelect');
                if (!sel) return;
                document.querySelectorAll('a.js-buy-to-confirm').forEach(function (a) {
                    a.addEventListener('click', function () {
                        try {
                            const u = new URL(a.getAttribute('href'), window.location.origin);
                            if (sel.value) {
                                u.searchParams.set('region', sel.value);
                            }
                            a.setAttribute('href', u.pathname + u.search);
                        } catch (e) {}
                    });
                });
            })();
        </script>
    @endif
@endauth
@endsection
