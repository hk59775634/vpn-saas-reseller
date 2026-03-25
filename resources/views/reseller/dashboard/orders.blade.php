@extends('layouts.reseller')

@section('title', '订单列表')
@section('header_title', '订单列表')

@section('sidebar')
    @include('reseller.partials.sidebar')
@endsection

@section('content')
<div x-data="resellerOrders()" x-init="init()" class="console-table-wrap" x-cloak>
    <div class="console-card-header">
        <h3 class="console-card-title">订单列表</h3>
    </div>
    <div class="console-filter-bar">
        <input type="text" placeholder="搜索订单..." class="console-filter-input max-w-xs" disabled aria-label="搜索（占位）">
    </div>
    <div class="overflow-x-auto">
        <table class="console-table">
            <thead>
                <tr>
                    <th>业务订单号</th>
                    <th>用户</th>
                    <th>产品</th>
                    <th>金额</th>
                    <th>状态</th>
                    <th>到期</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="o in orders" :key="o.id">
                    <tr>
                        <td class="font-mono text-xs" :title="o.biz_order_no || ''" x-text="shortBizOrderNo(o.biz_order_no)"></td>
                        <td class="max-w-[200px] truncate" :title="o.user ? o.user.email : ''"><span x-text="o.user ? (o.user.name + ' / ' + o.user.email) : '-'"></span></td>
                        <td x-text="o.reseller_product ? o.reseller_product.name : '-'"></td>
                        <td x-text="o.amount_cents ? (o.amount_cents/100 + ' 元') : '-'"></td>
                        <td x-text="o.status === 'paid' ? '已支付' : (o.status === 'pending' ? '待支付' : o.status)"></td>
                        <td class="whitespace-nowrap" x-text="o.expires_at ? new Date(o.expires_at).toLocaleString() : '-'"></td>
                        <td>
                            <button type="button" class="console-link text-xs" @click="orderDetailId = orderDetailId === o.id ? null : o.id" x-text="orderDetailId === o.id ? '收起' : '详情'"></button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    <div x-show="orderDetailId" class="mx-4 mb-4 rounded border border-slate-200 bg-slate-50 p-3 text-sm">
        <template x-if="selectedOrderRow()">
            <dl class="grid gap-2 sm:grid-cols-2">
                <div><dt class="text-slate-500">完整业务单号</dt><dd class="font-mono text-xs break-all" x-text="selectedOrderRow().biz_order_no || '—'"></dd></div>
                <div><dt class="text-slate-500">B 内部 ID</dt><dd class="font-mono" x-text="'#' + selectedOrderRow().id"></dd></div>
                <div><dt class="text-slate-500">A 内部订单 ID</dt><dd class="font-mono" x-text="selectedOrderRow().a_order_id ? ('#' + selectedOrderRow().a_order_id) : '—'"></dd></div>
                <div><dt class="text-slate-500">开通</dt><dd x-text="selectedOrderRow().activated_at ? new Date(selectedOrderRow().activated_at).toLocaleString() : '—'"></dd></div>
                <div><dt class="text-slate-500">最后续费</dt><dd x-text="selectedOrderRow().last_renewed_at ? new Date(selectedOrderRow().last_renewed_at).toLocaleString() : '—'"></dd></div>
                <div><dt class="text-slate-500">下单时间</dt><dd x-text="selectedOrderRow().created_at ? new Date(selectedOrderRow().created_at).toLocaleString() : '—'"></dd></div>
            </dl>
        </template>
    </div>
    <p x-show="orders.length === 0 && !loading" class="py-6 text-center text-sm text-slate-500">暂无订单</p>
</div>

@push('scripts')
<script>
function resellerOrders() {
    const api = (path, opts = {}) => {
        const token = localStorage.getItem('reseller_token');
        const h = { 'Content-Type': 'application/json', 'Accept': 'application/json', ...(opts.headers || {}) };
        if (token) h['Authorization'] = 'Bearer ' + token;
        const url = path[0] === '/' ? path : '/api/v1/' + path;
        return fetch(url, { ...opts, headers: h })
            .then(async r => {
                const body = await r.json().catch(() => ({}));
                if (r.status === 401 || r.status === 403) {
                    localStorage.removeItem('reseller_token');
                    localStorage.removeItem('reseller_name');
                    window.location.href = '/reseller/login?reason=' + (r.status === 403 ? 'forbidden' : 'unauthorized');
                    throw new Error(body.message || '请重新登录');
                }
                if (!r.ok) throw new Error(body.message || body.error || r.statusText);
                if (body && typeof body === 'object' && Object.prototype.hasOwnProperty.call(body, 'success') && Object.prototype.hasOwnProperty.call(body, 'data')) {
                    if (body.success === false) throw new Error(body.message || '请求失败');
                    return body.data;
                }
                return body;
            });
    };
    return {
        loading: false,
        orders: [],
        orderDetailId: null,
        shortBizOrderNo(s) {
            s = s ? String(s) : '';
            if (!s) return '—';
            if (s.length <= 16) return s;
            return s.slice(0, 10) + '…' + s.slice(-6);
        },
        selectedOrderRow() {
            const id = this.orderDetailId;
            if (!id) return null;
            return (this.orders || []).find(o => o && o.id === id) || null;
        },
        init() {
            if (!localStorage.getItem('reseller_token')) { window.location.href = '/reseller/login'; return; }
            this.loadOrders();
        },
        loadOrders() { api('reseller/orders').then(d => { this.orders = d || []; }).catch(() => {}); },
    };
}
</script>
@endpush
@endsection

