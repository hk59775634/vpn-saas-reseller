@extends('layouts.user')

@section('title', '订单流水')

@section('content')
@php
    $shortBiz = function ($s) {
        $s = (string) ($s ?? '');
        if ($s === '') {
            return '—';
        }
        if (strlen($s) <= 16) {
            return $s;
        }
        return substr($s, 0, 10) . '…' . substr($s, -6);
    };
@endphp
<section class="page-section">
    <h1 class="page-title">订单流水</h1>
    <p class="page-desc">每笔收入记录（新购、续费）。已开通服务请在 <a href="{{ route('user.subscriptions') }}" class="console-link font-medium">已购产品</a> 查看与续费。</p>
</section>
@if($orders->isEmpty())
    <div class="console-card p-8 text-center">
        <p class="text-slate-500">暂无订单。 <a href="{{ route('user.products') }}" class="console-link">去选购</a></p>
    </div>
@else
    <div class="console-table-wrap">
        <div class="overflow-x-auto">
            <table class="console-table">
                <thead>
                    <tr>
                        <th>业务订单号</th>
                        <th>产品</th>
                        <th>金额</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $o)
                    <tr>
                        <td class="font-mono text-xs" title="{{ $o->biz_order_no ?? '' }}">{{ $shortBiz($o->biz_order_no) }}</td>
                        <td>{{ $o->resellerProduct?->name ?? '-' }}</td>
                        <td>{{ number_format($o->amount_cents / 100, 2) }} 元</td>
                        <td>
                            @if($o->status === 'pending')
                                <span class="console-badge warning">待支付</span>
                            @elseif($o->status === 'paid')
                                <span class="console-badge success">已支付</span>
                            @else
                                {{ $o->status }}
                            @endif
                        </td>
                        <td class="space-y-1">
                            @if($o->status === 'pending')
                                @if(!empty($epay_enabled) && !empty($epay_ready))
                                    <form method="POST" action="{{ route('user.orders.pay.epay', $o->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="console-link font-medium">易支付</button>
                                    </form>
                                @endif
                                @if(!empty($allow_simulated_payment))
                                    <form method="POST" action="{{ route('user.orders.pay', $o->id) }}" class="inline @if(!empty($epay_enabled) && !empty($epay_ready)) ml-2 @endif">
                                        @csrf
                                        <button type="submit" class="console-link font-medium">模拟支付</button>
                                    </form>
                                @endif
                                @if(empty($epay_enabled) || empty($epay_ready))
                                    @if(empty($allow_simulated_payment))
                                        <span class="text-xs text-slate-500">暂无可用支付方式</span>
                                    @endif
                                @endif
                            @elseif($o->status === 'paid' && !$o->user_vpn_subscription_id)
                                <form method="POST" action="{{ route('user.orders.retry_provision', $o->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="console-link font-medium text-amber-700">补开通</button>
                                </form>
                            @else
                                —
                            @endif
                            <details class="mt-1 text-xs">
                                <summary class="cursor-pointer text-slate-500 hover:text-slate-800 select-none">详情</summary>
                                <dl class="mt-2 space-y-1 rounded border border-slate-200 bg-slate-50 p-2 text-left text-slate-700">
                                    <div><dt class="inline text-slate-500">完整业务单号</dt> <dd class="inline font-mono break-all">{{ $o->biz_order_no ?? '—' }}</dd></div>
                                    <div><dt class="inline text-slate-500">B 订单 ID</dt> <dd class="inline font-mono">#{{ $o->id }}</dd></div>
                                    <div><dt class="inline text-slate-500">关联已购产品</dt> <dd class="inline">{{ $o->user_vpn_subscription_id ? ('#' . $o->user_vpn_subscription_id) : '—' }}</dd></div>
                                    <div><dt class="inline text-slate-500">A 订阅 ID（快照）</dt> <dd class="inline font-mono">{{ $o->vpnSubscription?->a_order_id ? ('#' . $o->vpnSubscription->a_order_id) : '—' }}</dd></div>
                                    <div><dt class="inline text-slate-500">支付时间</dt> <dd class="inline">{{ optional($o->paid_at)->format('Y-m-d H:i') ?? '—' }}</dd></div>
                                    <div><dt class="inline text-slate-500">创建时间</dt> <dd class="inline">{{ $o->created_at->format('Y-m-d H:i') }}</dd></div>
                                </dl>
                            </details>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
