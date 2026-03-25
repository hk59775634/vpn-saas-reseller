@extends('layouts.reseller')

@section('title', '产品管理')
@section('header_title', '产品管理')

@section('sidebar')
    @include('reseller.partials.sidebar')
@endsection

@section('content')
<div x-data="resellerProducts()" x-init="init()" x-cloak class="space-y-6">
    {{-- 说明 --}}
    <div class="console-alert-info">
        <p class="font-semibold text-slate-900">同步 A 站产品</p>
        <p class="mt-1 text-sm leading-relaxed text-sky-900/90">
            列表与成本价来自 A 站公开产品；您只需设置「我的售价」与描述即可在 B 站前台售卖，无需在 B 站单独创建商品。
        </p>
    </div>

    {{-- 概览统计 --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="console-stat-card">
            <p class="console-stat-label">A 站可售产品</p>
            <p class="console-stat-value" x-text="stats().total"></p>
        </div>
        <div class="console-stat-card">
            <p class="console-stat-label">已配置售价</p>
            <p class="console-stat-value accent" x-text="stats().configured"></p>
        </div>
        <div class="console-stat-card">
            <p class="console-stat-label">前台在售</p>
            <p class="console-stat-value" x-text="stats().onSale"></p>
        </div>
    </div>

    <div class="console-table-wrap">
        <div class="console-card-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="console-card-title">产品与售价</h3>
                <p class="mt-0.5 text-xs text-slate-500">成本为 A 站定价；售价为您在 B 站的零售价</p>
            </div>
            <div class="w-full sm:w-72">
                <label class="sr-only">搜索产品</label>
                <input
                    type="search"
                    x-model="filter"
                    class="console-filter-input w-full"
                    placeholder="按产品名称筛选…"
                    autocomplete="off"
                >
            </div>
        </div>

        {{-- 加载骨架 --}}
        <div x-show="loading" class="divide-y divide-slate-100 px-2 py-2">
            <template x-for="n in [1,2,3,4,5]" :key="n">
                <div class="flex items-center gap-4 px-3 py-4">
                    <div class="h-4 flex-1 max-w-xs animate-pulse rounded bg-slate-100"></div>
                    <div class="h-4 w-16 animate-pulse rounded bg-slate-100"></div>
                    <div class="h-4 w-16 animate-pulse rounded bg-slate-100"></div>
                    <div class="h-4 w-12 animate-pulse rounded bg-slate-100"></div>
                </div>
            </template>
        </div>

        <div x-show="!loading" class="overflow-x-auto">
            <table class="console-table min-w-[720px]" id="rb-prod-table">
                <thead>
                    <tr>
                        <th class="min-w-[10rem]">A 站产品</th>
                        <th>成本价</th>
                        <th>我的售价</th>
                        <th>时长</th>
                        <th>状态</th>
                        <th class="text-right">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in filterRows()" :key="row.a_product_id">
                        <tr>
                            <td>
                                <p class="font-medium text-slate-900" x-text="row.my_product?.name || row.a_name"></p>
                                <p class="mt-0.5 text-[11px] text-slate-500" x-show="row.my_product?.name && row.my_product.name !== row.a_name">
                                    A 站原名：<span x-text="row.a_name"></span>
                                </p>
                            </td>
                            <td class="tabular-nums text-slate-600" x-text="formatYuan(row.cost_cents)"></td>
                            <td class="tabular-nums">
                                <template x-if="row.my_product">
                                    <span class="font-medium text-slate-900" x-text="formatYuan(row.my_product.price_cents)"></span>
                                </template>
                                <template x-if="!row.my_product">
                                    <span class="text-slate-400">未设置</span>
                                </template>
                            </td>
                            <td class="text-slate-600" x-text="(row.duration_days || 0) + ' 天'"></td>
                            <td>
                                <template x-if="row.my_product">
                                    <span
                                        class="console-badge"
                                        :class="row.my_product.status === 'active' ? 'success' : 'warning'"
                                        x-text="row.my_product.status === 'active' ? '在售' : '已下架'"
                                    ></span>
                                </template>
                                <template x-if="!row.my_product">
                                    <span class="text-xs text-slate-400">—</span>
                                </template>
                            </td>
                            <td class="text-right">
                                <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                    <template x-if="row.my_product">
                                        <span class="inline-flex gap-2">
                                            <button type="button" class="console-link text-xs font-medium" @click="startEdit(row)">编辑</button>
                                            <button
                                                type="button"
                                                class="text-xs font-medium text-red-600 transition hover:text-red-700 hover:underline"
                                                @click="deleteMyProduct(row.my_product.id)"
                                            >取消售卖</button>
                                        </span>
                                    </template>
                                    <template x-if="!row.my_product">
                                        <button type="button" class="console-btn-primary px-3 py-1.5 text-xs" @click="startSet(row)">设置售价</button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div
            x-show="!loading && merged.length === 0"
            class="border-t border-slate-100 px-6 py-14 text-center"
        >
            <div class="mx-auto max-w-sm">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-slate-800">暂无 A 站产品</p>
                <p class="mt-2 text-xs leading-relaxed text-slate-500">
                    请确认 A 站已配置公开在售产品，且 B 站 API Keys 中 A 站地址与密钥正确。
                </p>
            </div>
        </div>

        <p
            x-show="!loading && merged.length > 0 && filterRows().length === 0"
            class="border-t border-slate-100 px-6 py-8 text-center text-sm text-slate-500"
        >
            没有名称匹配「<span class="font-mono text-slate-700" x-text="filter"></span>」的产品，请调整筛选关键词。
        </p>
    </div>

    {{-- 设置弹窗 --}}
    <div
        x-show="showModal"
        x-cloak
        class="console-modal-backdrop"
        style="display: none;"
        @keydown.escape.window="closeModal()"
    >
        <div class="console-modal-panel" role="dialog" aria-modal="true">
            <div class="console-modal-header flex items-start justify-between gap-3">
                <div>
                    <h3 class="console-modal-title">配置 B 站售卖</h3>
                    <p class="mt-1 text-xs text-slate-500" x-text="editing ? ('A站原名：' + editing.a_name) : ''"></p>
                </div>
                <button
                    type="button"
                    class="rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    @click="closeModal()"
                    aria-label="关闭"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="console-modal-body space-y-4 text-sm">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="console-modal-label">前台展示名称</label>
                        <input
                            type="text"
                            x-model="editing.name"
                            class="console-modal-input"
                            placeholder="例如：高速稳定套餐（香港）"
                        >
                    </div>
                    <div>
                        <label class="console-modal-label">售价（元）</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            x-model="editing.price_yuan"
                            class="console-modal-input"
                            placeholder="例如：99.00"
                        >
                    </div>
                    <div>
                        <label class="console-modal-label">状态</label>
                        <select x-model="editing.status" class="console-modal-input">
                            <option value="active">在售（前台可见）</option>
                            <option value="disabled">已下架（前台隐藏）</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="console-modal-label">产品描述（Markdown / HTML，可选）</label>
                    <textarea
                        x-model="editing.description"
                        rows="10"
                        class="console-modal-textarea max-h-64"
                        placeholder="例如：适合多设备同时在线。支持 **Markdown**，也可使用 &lt;strong&gt;HTML&lt;/strong&gt;。"
                    ></textarea>
                    <p class="mt-1.5 text-[11px] leading-relaxed text-slate-500">
                        将展示在 B 站用户端产品卡片中。仅「在售」时前台可见。
                    </p>
                </div>
            </div>
            <div class="console-modal-footer">
                <button type="button" class="console-btn-secondary" @click="closeModal()">取消</button>
                <button type="button" class="console-btn-primary" @click="saveEditing()">保存</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function resellerProducts() {
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
                if (r.status === 204) return null;
                if (body && typeof body === 'object' && Object.prototype.hasOwnProperty.call(body, 'success') && Object.prototype.hasOwnProperty.call(body, 'data')) {
                    if (body.success === false) throw new Error(body.message || '请求失败');
                    return body.data;
                }
                return body;
            });
    };
    return {
        loading: false,
        merged: [],
        filter: '',
        editing: null,
        showModal: false,
        formatYuan(cents) {
            const n = Number(cents) || 0;
            return (n / 100).toFixed(2) + ' 元';
        },
        stats() {
            const m = this.merged || [];
            const configured = m.filter(r => r.my_product).length;
            const onSale = m.filter(r => r.my_product && r.my_product.status === 'active').length;
            return { total: m.length, configured, onSale };
        },
        filterRows() {
            const m = this.merged || [];
            const q = (this.filter || '').trim().toLowerCase();
            if (!q) return m;
            return m.filter(r => {
                const a = (r.a_name || '').toLowerCase();
                const n = (r.my_product?.name || '').toLowerCase();
                return a.includes(q) || n.includes(q);
            });
        },
        init() {
            if (!localStorage.getItem('reseller_token')) { window.location.href = '/reseller/login'; return; }
            this.loadMerged();
        },
        loadMerged() {
            this.loading = true;
            api('reseller/products_merged')
                .then(d => { this.merged = d || []; })
                .catch(() => { this.merged = []; })
                .finally(() => { this.loading = false; });
        },
        startSet(row) {
            this.editing = {
                a_product_id: row.a_product_id,
                a_name: row.a_name,
                name: (row.my_product && row.my_product.name) || row.a_name || '',
                my_id: null,
                price_yuan: '',
                status: 'active',
                description: (row.my_product && row.my_product.description) || '',
            };
            this.showModal = true;
        },
        startEdit(row) {
            const mp = row.my_product || {};
            this.editing = {
                a_product_id: row.a_product_id,
                a_name: row.a_name,
                name: mp.name || row.a_name || '',
                my_id: mp.id || null,
                price_yuan: mp.price_cents != null ? (mp.price_cents / 100).toFixed(2) : '',
                status: mp.status || 'active',
                description: mp.description || '',
            };
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.editing = null;
        },
        async saveEditing() {
            if (!this.editing) return;
            const priceYuan = parseFloat(this.editing.price_yuan);
            if (isNaN(priceYuan) || priceYuan < 0) {
                alert('请填写有效售价（元）');
                return;
            }
            const priceCents = Math.round(priceYuan * 100);
            const token = localStorage.getItem('reseller_token');
            const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
            if (token) headers['Authorization'] = 'Bearer ' + token;

            const isEdit = !!this.editing.my_id;
            const url = isEdit
                ? '/api/v1/reseller/products/' + this.editing.my_id
                : '/api/v1/reseller/products';
            const method = isEdit ? 'PUT' : 'POST';
            const body = isEdit
                ? JSON.stringify({
                    name: this.editing.name || null,
                    price_cents: priceCents,
                    status: this.editing.status || 'active',
                    description: this.editing.description || null,
                })
                : JSON.stringify({
                    source_product_id: this.editing.a_product_id,
                    name: this.editing.name || null,
                    price_cents: priceCents,
                    description: this.editing.description || null,
                });

            try {
                const r = await fetch(url, { method, headers, credentials: 'include', body });
                if (!r.ok) {
                    const b = await r.json().catch(() => ({}));
                    throw new Error(b.message || b.error || r.statusText || '保存失败');
                }
                this.closeModal();
                this.loadMerged();
            } catch (e) {
                alert(e.message || '保存失败');
            }
        },
        async deleteMyProduct(id) {
            if (!id || !confirm('确定取消售卖该产品？仅移除 B 站售价配置，不影响 A 站产品。')) return;
            const token = localStorage.getItem('reseller_token');
            const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
            if (token) headers['Authorization'] = 'Bearer ' + token;
            try {
                const r = await fetch('/api/v1/reseller/products/' + id, {
                    method: 'DELETE',
                    headers,
                    credentials: 'include',
                });
                if (!r.ok && r.status !== 204) {
                    const b = await r.json().catch(() => ({}));
                    throw new Error(b.message || '删除失败');
                }
                this.editing = null;
                this.loadMerged();
            } catch (e) {
                alert(e.message || '删除失败');
            }
        },
    };
}
</script>
@endpush
@endsection
