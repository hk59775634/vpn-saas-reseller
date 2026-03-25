@extends('layouts.reseller')

@section('title', '分销商中心')
@section('header_title', '概览')

@section('sidebar')
    @include('reseller.partials.sidebar')
@endsection

@section('content')
<div x-data="resellerOverview()" x-init="init()" class="space-y-6">
    {{-- 仪表板统计卡片 --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="console-stat-card">
            <p class="console-stat-label">本站用户数</p>
            <p class="console-stat-value" x-text="stats.users_count ?? '-'"></p>
        </div>
        <div class="console-stat-card">
            <p class="console-stat-label">订单数</p>
            <p class="console-stat-value" x-text="stats.orders_count ?? '-'"></p>
        </div>
        <div class="console-stat-card">
            <p class="console-stat-label">已支付金额</p>
            <p class="console-stat-value accent" x-text="stats.paid_amount_cents != null ? (stats.paid_amount_cents/100).toFixed(2) + ' 元' : '-'"></p>
        </div>
    </div>
    <div class="console-card max-w-2xl p-6">
        <h3 class="mb-4 font-semibold text-slate-900">我的信息</h3>
        <dl class="space-y-3 text-sm">
            <div><dt class="text-slate-500">ID</dt><dd class="font-medium text-slate-800" x-text="me.id ?? '-'"></dd></div>
            <div><dt class="text-slate-500">名称</dt><dd class="text-slate-800" x-text="me.name ?? '-'"></dd></div>
        </dl>
        <p x-show="loading" class="mt-2 text-slate-500">加载中…</p>
        <p x-show="!loading && !me.id" class="mt-2 text-amber-700 text-sm">未能加载分销商信息，请检查 A 站 API Key 或网络。</p>
    </div>
    <div class="console-alert-info max-w-2xl">
        <p class="font-semibold text-slate-900 mb-1">使用说明</p>
        <p>API Key 由管理员在 A 站「分销商」中为您的账号生成。本后台用于管理在 B 站配置的产品、用户与订单，以及查看名下 API Keys。</p>
    </div>
</div>

@push('scripts')
<script>
function resellerOverview() {
    const api = (path) => {
        const token = localStorage.getItem('reseller_token');
        const h = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
        if (token) h['Authorization'] = 'Bearer ' + token;
        const url = path[0] === '/' ? path : '/api/v1/' + path;
        return fetch(url, { headers: h })
            .then(async r => {
                const body = await r.json().catch(() => ({}));
                if (r.status === 401 || r.status === 403) {
                    localStorage.removeItem('reseller_token');
                    localStorage.removeItem('reseller_name');
                    window.location.href = '/reseller/login?reason=' + (r.status === 403 ? 'forbidden' : 'unauthorized');
                    throw new Error(body.message || '请重新登录');
                }
                if (!r.ok) throw new Error(body.message || body.error || r.statusText);
                if (r.status === 204) return null;
                // 统一响应 { success, code, message, data }
                if (body && typeof body === 'object' && Object.prototype.hasOwnProperty.call(body, 'success') && Object.prototype.hasOwnProperty.call(body, 'data')) {
                    if (body.success === false) throw new Error(body.message || '请求失败');
                    return body.data;
                }
                return body;
            });
    };
    return {
        loading: false,
        me: {},
        stats: {},
        init() {
            if (!localStorage.getItem('reseller_token')) { window.location.href = '/reseller/login'; return; }
            this.loading = true;
            Promise.all([
                api('reseller/me').then(d => { this.me = d || {}; }),
                api('reseller/stats').then(d => { this.stats = d || {}; })
            ]).catch(e => { if (e.message) alert(e.message); })
                .finally(() => { this.loading = false; });
        }
    };
}
</script>
@endpush
@endsection
