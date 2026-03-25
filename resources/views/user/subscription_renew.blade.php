@extends('layouts.user')

@section('title', '续费')

@section('content')
@php
    $renewAlpine = [
        'daily' => $renewUnitDays === 1,
        'unitCents' => (int) $product->price_cents,
        'days' => (int) old('renew_days', 1),
        'periods' => (int) old('renew_months', 1),
    ];
@endphp
<section class="page-section">
    <nav class="mb-4 text-sm text-slate-500">
        <a href="{{ route('user.subscriptions') }}" class="console-link">已购产品</a>
        <span class="mx-1">/</span>
        <span class="text-slate-800">续费</span>
    </nav>
    <h1 class="page-title">续费</h1>
    <p class="page-desc">选择续费时长后创建订单并支付；单位与产品计费周期一致（按天计费选天数，否则选周期数）。</p>
</section>

<div
    class="console-card p-6 sm:p-8"
    x-data='@json($renewAlpine)'
>
    <div class="border-b border-slate-100 pb-4">
        <h2 class="text-lg font-semibold text-slate-900">{{ $product->name }}</h2>
        <p class="mt-2 text-sm text-slate-600">
            区域 <span class="font-medium">{{ $subscription->region ?? '—' }}</span>
            · 当前到期 <span class="font-medium">{{ optional($subscription->expires_at)->format('Y-m-d H:i') ?? '—' }}</span>
        </p>
        <p class="mt-4 text-sm text-slate-600">
            单价 <span class="font-semibold text-sky-600">{{ number_format($product->price_cents / 100, 2) }}</span> 元
            / 每 {{ $renewUnitDays }} 天（一个计费周期）
        </p>
        <p class="mt-2 text-2xl font-semibold text-sky-600">
            应付合计：<span x-text="daily ? (days * unitCents / 100).toFixed(2) : (periods * unitCents / 100).toFixed(2)"></span> 元
            <span class="text-base font-normal text-slate-500" x-show="daily">（<span x-text="days"></span> 天）</span>
            <span class="text-base font-normal text-slate-500" x-show="!daily">（<span x-text="periods"></span> 个周期 × {{ $renewUnitDays }} 天）</span>
        </p>
    </div>

    <form method="POST" action="{{ route('user.subscriptions.renew', $subscription->id) }}" class="mt-6 space-y-6">
        @csrf

        @if($renewUnitDays === 1)
            <div>
                <label class="block text-sm font-medium text-slate-700" for="renew_days">续费天数</label>
                <input
                    type="number"
                    id="renew_days"
                    name="renew_days"
                    min="1"
                    max="365"
                    x-model.number="days"
                    value="{{ old('renew_days', 1) }}"
                    required
                    class="console-input mt-1 max-w-xs"
                >
                <p class="mt-1 text-xs text-slate-500">按天计费产品：填写 1～365 天。</p>
                @error('renew_days')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @else
            <div>
                <label class="block text-sm font-medium text-slate-700" for="renew_months">续费周期数</label>
                <select
                    id="renew_months"
                    name="renew_months"
                    x-model.number="periods"
                    class="console-input mt-1 max-w-md"
                    required
                >
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((int) old('renew_months', 1) === $m)>{{ $m }} 个周期（{{ $m * $renewUnitDays }} 天）</option>
                    @endfor
                </select>
                <p class="mt-1 text-xs text-slate-500">每周期 {{ $renewUnitDays }} 天；可选 1～12 个周期。</p>
                @error('renew_months')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @endif

        <div class="flex flex-wrap gap-3 pt-2">
            <a href="{{ route('user.subscriptions') }}" class="console-btn-secondary">返回已购产品</a>
            <button type="submit" class="console-btn-primary">确认并创建续费订单</button>
        </div>
    </form>
</div>
@endsection
