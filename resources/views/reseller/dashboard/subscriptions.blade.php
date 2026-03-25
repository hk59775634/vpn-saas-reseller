@extends('layouts.reseller')

@section('title', '已购产品')
@section('header_title', '已购产品管理')

@section('sidebar')
    @include('reseller.partials.sidebar')
@endsection

@section('content')
<div x-data="resellerSubscriptions()" x-init="init()" class="console-table-wrap" x-cloak>
    <div class="console-card-header">
        <div>
            <h3 class="console-card-title">已购产品</h3>
            <p class="mt-1 text-xs text-slate-500">按订阅维度展示用户已开通的产品、到期时间与 A 站订单号；详情中可查看关联订单、SSL VPN 与 WireGuard 配置（由 A 站生成）。</p>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="console-table">
            <thead>
                <tr>
                    <th>订阅 ID</th>
                    <th>用户</th>
                    <th>产品</th>
                    <th>区域</th>
                    <th>A 站订单</th>
                    <th>状态</th>
                    <th>到期</th>
                    <th>订单笔数</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="s in subscriptions" :key="s.id">
                    <tr>
                        <td class="font-mono text-xs" x-text="'#' + s.id"></td>
                        <td class="max-w-[200px] truncate" :title="s.user ? s.user.email : ''">
                            <span x-text="s.user ? (s.user.name + ' / ' + s.user.email) : '-'"></span>
                        </td>
                        <td x-text="s.reseller_product ? s.reseller_product.name : '-'"></td>
                        <td x-text="s.region || '—'"></td>
                        <td class="font-mono text-xs" x-text="s.a_order_id ? ('#' + s.a_order_id) : '—'"></td>
                        <td x-text="s.status === 'active' ? '有效' : (s.status || '—')"></td>
                        <td class="whitespace-nowrap" x-text="s.expires_at ? new Date(s.expires_at).toLocaleString() : '—'"></td>
                        <td x-text="s.orders_count ?? 0"></td>
                        <td>
                            <button type="button" class="console-link text-xs" @click="openDetail(s.id)">详情</button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    <p x-show="subscriptions.length === 0 && !loading" class="py-6 text-center text-sm text-slate-500">暂无已购订阅</p>

    {{-- 详情：宽度与「侧栏右侧」主内容区一致（桌面 100vw - 16rem），全高可滚动 --}}
    <div x-show="detailId" x-cloak class="fixed inset-0 z-50 flex min-h-0 flex-row overflow-hidden">
        {{-- 与左侧导航同宽的占位，使右侧区域与点开前列表所在主栏对齐 --}}
        <div class="hidden shrink-0 md:block md:w-64" aria-hidden="true"></div>
        <div class="relative flex min-h-0 min-w-0 flex-1 flex-col">
            <div class="absolute inset-0 z-0 bg-black/40" @click="detailId = null" aria-hidden="true"></div>
            <aside class="relative z-10 flex h-full max-h-[100dvh] min-h-0 w-full flex-col border-l border-slate-200 bg-white shadow-xl">
            <header class="flex shrink-0 items-center justify-between border-b border-slate-100 bg-white px-3 py-2">
                <h3 class="text-sm font-semibold text-slate-900">订阅详情</h3>
                <button type="button" class="rounded p-0.5 text-xl leading-none text-slate-500 hover:bg-slate-100 hover:text-slate-800" @click="detailId = null" aria-label="关闭">&times;</button>
            </header>
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain">
            <div class="px-3 py-2 text-xs" x-show="detailLoading">
                <p class="text-slate-500">加载中…</p>
            </div>
            <div x-show="!detailLoading && detail" class="space-y-2 px-3 py-2 pb-4 text-xs leading-snug">
                    {{-- 订阅 / 用户 / 产品：合并为一块多列栅格，减少纵向占用 --}}
                    <div class="rounded border border-slate-200 bg-slate-50/80 p-2">
                        <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">基本信息</p>
                        <dl class="grid grid-cols-2 gap-x-3 gap-y-1 sm:grid-cols-3 lg:grid-cols-4">
                            <div class="min-w-0"><dt class="text-[11px] text-slate-500">订阅 ID</dt><dd class="truncate font-mono text-slate-800" x-text="'#' + detail.subscription.id"></dd></div>
                            <div class="min-w-0"><dt class="text-[11px] text-slate-500">状态</dt><dd class="text-slate-800" x-text="detail.subscription.status"></dd></div>
                            <div class="min-w-0"><dt class="text-[11px] text-slate-500">A 站订单</dt><dd class="truncate font-mono text-slate-800" x-text="detail.subscription.a_order_id ? ('#' + detail.subscription.a_order_id) : '—'"></dd></div>
                            <div class="min-w-0"><dt class="text-[11px] text-slate-500">区域</dt><dd class="text-slate-800" x-text="detail.subscription.region || '—'"></dd></div>
                            <div class="min-w-0 sm:col-span-2"><dt class="text-[11px] text-slate-500">开通</dt><dd class="break-all text-slate-800" x-text="detail.subscription.activated_at ? new Date(detail.subscription.activated_at).toLocaleString() : '—'"></dd></div>
                            <div class="min-w-0 sm:col-span-2"><dt class="text-[11px] text-slate-500">最后续费</dt><dd class="break-all text-slate-800" x-text="detail.subscription.last_renewed_at ? new Date(detail.subscription.last_renewed_at).toLocaleString() : '—'"></dd></div>
                            <div class="min-w-0 sm:col-span-2 lg:col-span-4"><dt class="text-[11px] text-slate-500">到期</dt><dd class="break-all text-slate-800" x-text="detail.subscription.expires_at ? new Date(detail.subscription.expires_at).toLocaleString() : '—'"></dd></div>
                            <div class="min-w-0"><dt class="text-[11px] text-slate-500">用户</dt><dd class="truncate text-slate-800" x-text="detail.subscription.user ? detail.subscription.user.name : '—'"></dd></div>
                            <div class="min-w-0 sm:col-span-2 lg:col-span-2"><dt class="text-[11px] text-slate-500">邮箱</dt><dd class="break-all text-slate-800" x-text="detail.subscription.user ? detail.subscription.user.email : '—'"></dd></div>
                            <div class="min-w-0 sm:col-span-2" x-show="detail.subscription.reseller_product"><dt class="text-[11px] text-slate-500">产品</dt><dd class="truncate text-slate-800" x-text="detail.subscription.reseller_product ? detail.subscription.reseller_product.name : ''"></dd></div>
                            <div class="min-w-0" x-show="detail.subscription.reseller_product"><dt class="text-[11px] text-slate-500">售价</dt><dd class="text-slate-800" x-text="detail.subscription.reseller_product ? (detail.subscription.reseller_product.price_cents != null ? (detail.subscription.reseller_product.price_cents/100) + ' ' + (detail.subscription.reseller_product.currency || '') : '—') : ''"></dd></div>
                            <div class="min-w-0" x-show="detail.subscription.reseller_product"><dt class="text-[11px] text-slate-500">A 站产品 ID</dt><dd class="font-mono text-slate-800" x-text="detail.subscription.reseller_product ? detail.subscription.reseller_product.source_product_id : ''"></dd></div>
                        </dl>
                    </div>

                    <div class="rounded border border-slate-200 bg-slate-50/70 p-2" x-show="detail.provision_progress">
                        <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">开通进度</p>
                        <div class="space-y-1">
                            <p class="text-[11px]"><span class="text-slate-500">A 站</span>：<span class="font-mono text-slate-900" x-text="detail.provision_progress.a_order || '—'"></span></p>
                            <p class="text-[11px]"><span class="text-slate-500">SSL VPN</span>：<span class="font-mono text-slate-900" x-text="detail.provision_progress.sslvpn || '—'"></span></p>
                            <p class="text-[11px]"><span class="text-slate-500">WireGuard</span>：<span class="font-mono text-slate-900" x-text="detail.provision_progress.wireguard || '—'"></span></p>
                        </div>
                    </div>
                    <div class="rounded border border-slate-200 p-2">
                        <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">关联订单（B 站）</p>
                        <div class="max-h-28 overflow-auto rounded border border-slate-100 bg-white">
                            <table class="w-full text-[11px]">
                                <thead class="sticky top-0 bg-slate-50 text-left text-slate-600"><tr><th class="px-1.5 py-0.5">#</th><th class="px-1.5 py-0.5">业务单号</th><th class="px-1.5 py-0.5">金额</th><th class="px-1.5 py-0.5">状态</th></tr></thead>
                                <tbody>
                                    <template x-for="o in (detail.subscription.orders || [])" :key="o.id">
                                        <tr class="border-t border-slate-100">
                                            <td class="px-1.5 py-0.5 font-mono" x-text="'#' + o.id"></td>
                                            <td class="px-1.5 py-0.5 font-mono break-all" x-text="o.biz_order_no || '—'"></td>
                                            <td class="px-1.5 py-0.5 whitespace-nowrap" x-text="o.amount_cents != null ? (o.amount_cents/100) + ' 元' : '—'"></td>
                                            <td class="px-1.5 py-0.5" x-text="o.status"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="rounded border border-sky-200/80 bg-sky-50/40 p-2" x-show="detail.enable_radius">
                        <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-sky-800">SSL VPN</p>
                        <div x-show="(detail.ssvpn && detail.ssvpn.login) || (detail.subscription && detail.subscription.radius_login)">
                            <div class="flex flex-col gap-1">
                                <div><span class="text-slate-500">登录名</span><span class="ml-2 font-mono text-slate-900" x-text="(detail.ssvpn && detail.ssvpn.login) ? detail.ssvpn.login : (detail.subscription ? detail.subscription.radius_login : '')"></span></div>
                                <div><span class="text-slate-500">密码</span><span class="ml-2 font-mono text-slate-900" x-text="(detail.ssvpn && detail.ssvpn.password) ? detail.ssvpn.password : (detail.subscription ? detail.subscription.sslvpn_password : '')"></span></div>
                                <div x-show="detail.ssvpn.gateway"><span class="text-slate-500">网关</span><span class="ml-2 text-slate-900" x-text="detail.ssvpn.gateway"></span></div>
                            </div>
                            <p class="mt-2 text-[11px] text-slate-600" x-show="detail.ssvpn.hint" x-text="detail.ssvpn.hint"></p>
                        </div>
                        <div class="text-[11px] text-slate-500" x-show="detail.enable_radius && !(((detail.ssvpn && detail.ssvpn.login && detail.ssvpn.password) || (detail.subscription && detail.subscription.radius_login && detail.subscription.sslvpn_password)))">
                            <p>当前产品包含 SSL VPN，但尚未同步登录信息，请稍后重试或联系客服。</p>
                            <button type="button" class="console-link mt-2 inline-flex text-xs" x-show="!syncLoading" @click="syncSsl(detail.subscription.id)">
                                一键同步 SSL VPN
                            </button>
                            <p class="mt-2 text-[11px] text-slate-600" x-show="syncLoading">同步中，请稍候…</p>
                        </div>
                    </div>

                    <div class="rounded border border-amber-200/80 bg-amber-50/40 p-2" x-show="detail.enable_wireguard">
                        <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-amber-900/80">WireGuard</p>
                        <p class="mb-1 text-[11px] text-amber-900/90" x-show="detail.wireguard_error" x-text="detail.wireguard_error"></p>
                        <div class="max-h-36 overflow-auto rounded border border-slate-300 bg-white p-2 shadow-inner" x-show="detail.wireguard && detail.wireguard.config">
                            <pre class="text-[11px] leading-relaxed text-slate-900 whitespace-pre-wrap break-all font-mono selection:bg-sky-200" x-text="detail.wireguard.config"></pre>
                        </div>
                        <p class="text-[11px] text-slate-500" x-show="!detail.wireguard_error && detail.wireguard && !detail.wireguard.config">暂无配置文本</p>
                    </div>
                </div>
            </div>
            </aside>
        </div>
    </div>
</div>

@push('scripts')
<script>
function resellerSubscriptions() {
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
        subscriptions: [],
        detailId: null,
        detail: null,
        detailLoading: false,
        syncLoading: false,
        init() {
            if (!localStorage.getItem('reseller_token')) { window.location.href = '/reseller/login'; return; }
            this.loadList();
        },
        loadList() {
            this.loading = true;
            api('reseller/subscriptions').then(d => { this.subscriptions = d || []; }).catch(() => {}).finally(() => { this.loading = false; });
        },
        openDetail(id) {
            this.detailId = id;
            this.detail = null;
            this.detailLoading = true;
            api('reseller/subscriptions/' + id).then(d => {
                this.detail = d;
            }).catch(e => {
                alert(e.message || '加载失败');
                this.detailId = null;
            }).finally(() => { this.detailLoading = false; });
        },
        async syncSsl(id) {
            if (!id) return;
            this.syncLoading = true;
            try {
                await api('reseller/subscriptions/' + id + '/sync-sslvpn', { method: 'POST' });
                await this.openDetail(id);
            } catch (e) {
                alert(e.message || '同步失败');
            } finally {
                this.syncLoading = false;
            }
        },
    };
}
</script>
@endpush
@endsection
