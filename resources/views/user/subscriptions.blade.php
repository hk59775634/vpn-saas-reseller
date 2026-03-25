@extends('layouts.user')

@section('title', '已购产品')

@section('content')
<div x-data="userSubscriptionsPortal()" class="space-y-6">
<section class="page-section">
    <h1 class="page-title">已购产品</h1>
    <p class="page-desc">当前账号下的 VPN 服务（与 A 站订阅对应）。续费会新增订单流水并延长到期。明细见 <a href="{{ route('user.orders') }}" class="console-link font-medium">订单流水</a>。</p>
</section>
@if($subscriptions->isEmpty())
    <div class="console-card p-8 text-center">
        <p class="text-slate-500">暂无已购产品。请先在 <a href="{{ route('user.products') }}" class="console-link">产品</a> 下单并完成支付。</p>
    </div>
@else
    <div class="console-table-wrap">
        <div class="overflow-x-auto">
            <table class="console-table">
                <thead>
                    <tr>
                        <th>产品</th>
                        <th>区域</th>
                        <th>A 订阅 ID</th>
                        <th>到期时间</th>
                        <th>最后续费</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subscriptions as $s)
                    <tr>
                        <td>{{ $s->resellerProduct?->name ?? '-' }}</td>
                        <td>{{ $s->region ?? '—' }}</td>
                        <td class="font-mono text-xs">{{ $s->a_order_id ? ('#' . $s->a_order_id) : '—' }}</td>
                        <td class="whitespace-nowrap text-slate-600">{{ optional($s->expires_at)->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="whitespace-nowrap text-slate-600">{{ optional($s->last_renewed_at)->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="flex flex-wrap gap-2">
                            @if($s->a_order_id)
                                <button type="button" class="console-link font-medium" @click="openDetail({{ (int) $s->id }})">详情</button>
                            @endif
                            @if($s->a_order_id && $s->resellerProduct && $s->resellerProduct->status === 'active')
                                <a href="{{ route('user.subscriptions.renew.show', $s->id) }}" class="console-link font-medium">续费</a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- 详情侧栏：按产品协议展示 SSL VPN 或 WireGuard --}}
    <div x-show="detailOpen" x-cloak class="fixed inset-0 z-50 flex min-h-0 flex-row overflow-hidden">
        <div class="hidden shrink-0 md:block md:w-8" aria-hidden="true"></div>
        <div class="relative flex min-h-0 min-w-0 flex-1 flex-col">
            <div class="absolute inset-0 z-0 bg-black/40" @click="closeDetail()" aria-hidden="true"></div>
            <aside class="relative z-10 ml-auto flex h-full max-h-[100dvh] min-h-0 w-full max-w-lg flex-col border-l border-slate-200 bg-white shadow-xl">
                <header class="flex shrink-0 items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h3 class="text-sm font-semibold text-slate-900">已购产品详情</h3>
                    <button type="button" class="rounded p-0.5 text-xl leading-none text-slate-500 hover:bg-slate-100" @click="closeDetail()" aria-label="关闭">&times;</button>
                </header>
                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3 text-xs leading-relaxed">
                    <template x-if="loading">
                        <p class="text-slate-500">加载中…</p>
                    </template>
                    <div x-show="!loading && detail" class="space-y-4">
                        <div class="rounded border border-slate-200 bg-slate-50/80 p-3">
                            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">基本信息</p>
                            <dl class="space-y-1">
                                <div><dt class="inline text-slate-500">产品</dt> <dd class="inline text-slate-800" x-text="detail?.subscription?.product_name"></dd></div>
                                <div><dt class="inline text-slate-500">区域</dt> <dd class="inline text-slate-800" x-text="detail?.subscription?.region || '—'"></dd></div>
                                <div><dt class="inline text-slate-500">A 站订单</dt> <dd class="inline font-mono text-slate-800" x-text="detail?.subscription?.a_order_id ? ('#' + detail.subscription.a_order_id) : '—'"></dd></div>
                            </dl>
                        </div>

                        <div class="rounded border border-sky-200/80 bg-sky-50/40 p-3" x-show="detail?.enable_radius && detail?.sslvpn">
                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-sky-800">SSL VPN 连接信息</p>
                            <dl class="space-y-1 break-all">
                                <div><dt class="text-slate-500">登录名</dt> <dd class="font-mono text-slate-900" x-text="detail?.sslvpn?.login"></dd></div>
                                <div><dt class="text-slate-500">密码</dt> <dd class="font-mono text-slate-900" x-text="detail?.sslvpn?.password || '—'"></dd></div>
                                <div x-show="detail?.sslvpn?.gateway"><dt class="text-slate-500">网关/服务器</dt> <dd class="text-slate-900" x-text="detail?.sslvpn?.gateway"></dd></div>
                            </dl>
                            <p class="mt-2 text-[11px] text-slate-600" x-text="detail?.sslvpn?.hint"></p>
                        </div>
                        <div class="rounded border border-amber-200 bg-amber-50/50 p-3 text-amber-900" x-show="detail?.enable_radius && !detail?.sslvpn">
                            <p class="text-[11px]">当前产品含 SSL VPN，但尚未同步登录信息。请稍后重试或联系客服。</p>
                            <button type="button" class="console-link mt-2 inline-flex text-xs" x-show="!syncLoading" @click="syncSsl(detail?.subscription?.id)">
                                一键同步 SSL VPN
                            </button>
                            <p class="mt-2 text-[11px] text-amber-800" x-show="syncLoading">同步中，请稍候…</p>
                        </div>

                        <div class="rounded border border-emerald-200/80 bg-emerald-50/30 p-3" x-show="detail?.enable_wireguard">
                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-emerald-900">WireGuard</p>
                            <p class="mb-1 text-[11px] text-amber-900" x-show="detail?.wireguard_error" x-text="detail?.wireguard_error"></p>
                            <div class="max-h-48 overflow-auto rounded border border-slate-300 bg-white p-2" x-show="detail?.wireguard?.config">
                                <pre class="whitespace-pre-wrap break-all font-mono text-[11px] text-slate-900" x-text="detail?.wireguard?.config"></pre>
                            </div>
                            <p class="mt-2 text-[11px] text-slate-500" x-show="detail?.enable_wireguard && !detail?.wireguard?.config && !detail?.wireguard_error">暂无配置文本</p>
                        </div>
                        <div class="rounded border border-slate-200 p-3 text-slate-600" x-show="!detail?.enable_radius && !detail?.enable_wireguard">
                            <p class="text-[11px]">未配置可展示的连接方式，请联系管理员检查 A 站产品属性。</p>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
@endif
</div>
@endsection

@push('scripts')
<script>
function userSubscriptionsPortal() {
    return {
        detailOpen: false,
        loading: false,
        syncLoading: false,
        detail: null,
        detailUrlBase: @json(url('/subscriptions')),
        async openDetail(id) {
            this.detailOpen = true;
            this.loading = true;
            this.detail = null;
            try {
                const url = this.detailUrlBase + '/' + id + '/detail-data';
                const r = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) {
                    const j = await r.json().catch(() => ({}));
                    throw new Error(j.message || ('HTTP ' + r.status));
                }
                const body = await r.json();
                this.detail = (body && typeof body === 'object' && Object.prototype.hasOwnProperty.call(body, 'success') && Object.prototype.hasOwnProperty.call(body, 'data'))
                    ? body.data
                    : body;
            } catch (e) {
                alert(e.message || '加载失败');
                this.closeDetail();
            } finally {
                this.loading = false;
            }
        },
        closeDetail() {
            this.detailOpen = false;
            this.detail = null;
        },
        async syncSsl(id) {
            if (!id) return;
            this.syncLoading = true;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const r = await fetch(this.detailUrlBase + '/' + id + '/sync-sslvpn', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({}),
                });
                const j = await r.json().catch(() => ({}));
                if (!r.ok) {
                    throw new Error(j.message || ('HTTP ' + r.status));
                }
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
