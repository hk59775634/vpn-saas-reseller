@extends('layouts.reseller')

@section('title', '分销商中心')

@section('sidebar')
<a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg text-emerald-200 hover:bg-white/10 hover:text-white transition mb-0.5" @click.prevent="tab = 'me'" :class="{ 'bg-white/15 text-white': tab === 'me' }">我的信息</a>
<a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg text-emerald-200 hover:bg-white/10 hover:text-white transition mb-0.5" @click.prevent="tab = 'products'; loadProducts(); loadAProducts()" :class="{ 'bg-white/15 text-white': tab === 'products' }">产品管理</a>
<a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg text-emerald-200 hover:bg-white/10 hover:text-white transition mb-0.5" @click.prevent="tab = 'users'; loadUsers()" :class="{ 'bg-white/15 text-white': tab === 'users' }">用户管理</a>
<a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg text-emerald-200 hover:bg-white/10 hover:text-white transition mb-0.5" @click.prevent="tab = 'orders'; loadOrders()" :class="{ 'bg-white/15 text-white': tab === 'orders' }">订单列表</a>
<a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg text-emerald-200 hover:bg-white/10 hover:text-white transition mb-0.5" @click.prevent="tab = 'api_keys'; loadApiKeys()" :class="{ 'bg-white/15 text-white': tab === 'api_keys' }">API Keys</a>
@endsection

@section('header_title', '分销商中心')

@section('content')
<div x-data="resellerDashboard()" x-init="init()" class="space-y-6">
    <div x-show="tab === 'me'" class="space-y-6 max-w-2xl">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl border border-zinc-200 p-4 shadow-sm">
                <p class="text-xs text-zinc-500 uppercase tracking-wider">本站用户数</p>
                <p class="mt-1 text-2xl font-semibold text-zinc-800" x-text="stats.users_count ?? '-'"></p>
            </div>
            <div class="bg-white rounded-xl border border-zinc-200 p-4 shadow-sm">
                <p class="text-xs text-zinc-500 uppercase tracking-wider">订单数</p>
                <p class="mt-1 text-2xl font-semibold text-zinc-800" x-text="stats.orders_count ?? '-'"></p>
            </div>
            <div class="bg-white rounded-xl border border-zinc-200 p-4 shadow-sm">
                <p class="text-xs text-zinc-500 uppercase tracking-wider">已支付金额</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-600" x-text="stats.paid_amount_cents != null ? (stats.paid_amount_cents/100).toFixed(2) + ' 元' : '-'"></p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-medium text-zinc-800 mb-4">我的信息</h3>
            <dl class="space-y-3 text-sm">
                <div><dt class="text-zinc-500">ID</dt><dd class="text-zinc-800 font-medium" x-text="me.id ?? '-'"></dd></div>
                <div><dt class="text-zinc-500">名称</dt><dd class="text-zinc-800" x-text="me.name ?? '-'"></dd></div>
            </dl>
            <p x-show="!me.id && !loading" class="text-zinc-500 mt-2">加载中…</p>
        </div>
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-sm text-zinc-600">
            <p class="font-medium text-zinc-800 mb-1">使用说明</p>
            <p>API Key 由管理员在 A 站「分销商」中为您的账号生成。本页可查看您名下的 API Key 列表。使用 API Key 可调用开放 API 管理 VPN 账号等，具体接口请参考 A 站提供的 API 文档。</p>
        </div>
    </div>

    <div x-show="tab === 'api_keys'" class="bg-white rounded-xl border border-zinc-200 overflow-hidden shadow-sm">
        <div class="px-5 py-3 border-b border-zinc-100">
            <h3 class="font-medium text-zinc-800">API Keys</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 text-zinc-600">
                    <tr>
                        <th class="text-left py-3 px-4">ID</th>
                        <th class="text-left py-3 px-4">名称</th>
                        <th class="text-left py-3 px-4">API Key</th>
                        <th class="text-left py-3 px-4">创建时间</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="k in apiKeys" :key="k.id">
                        <tr class="border-t border-zinc-100 hover:bg-zinc-50/50">
                            <td class="py-3 px-4" x-text="k.id"></td>
                            <td class="py-3 px-4" x-text="k.name || '-'"></td>
                            <td class="py-3 px-4 font-mono text-zinc-500" x-text="k.api_key || '-'"></td>
                            <td class="py-3 px-4" x-text="k.created_at ? new Date(k.created_at).toLocaleString() : '-'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <p x-show="apiKeys.length === 0 && !loading" class="py-6 text-center text-zinc-500">暂无 API Key</p>
    </div>

    {{-- 产品管理：基于 A 站产品组合为自有产品 --}}
    <div x-show="tab === 'products'" class="space-y-6">
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-4">
            <p class="text-sm text-zinc-500 mb-3">从 A 站产品中选择一个作为基础，设置您的名称、售价与时长，形成在 B 站前台展示的套餐。</p>
            <h3 class="font-medium text-zinc-800 mb-3">添加产品</h3>
            <div class="flex flex-wrap items-end gap-4">
                <div class="min-w-[220px]">
                    <label class="block text-xs text-zinc-500 mb-1">A 站基础产品</label>
                    <select x-model="formProduct.source_product_id" class="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm w-full">
                        <option value="">请选择</option>
                        <template x-for="a in aProducts" :key="a.id">
                            <option :value="a.id" x-text="a.name + ' (' + (a.price_cents||0) + '分/' + (a.duration_days||30) + '天)'"></option>
                        </template>
                    </select>
                </div>
                <div><label class="block text-xs text-zinc-500 mb-1">展示名称</label><input type="text" x-model="formProduct.name" class="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm w-40" placeholder="月付套餐"></div>
                <div><label class="block text-xs text-zinc-500 mb-1">价格（分）</label><input type="number" x-model="formProduct.price_cents" class="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm w-24" placeholder="9900"></div>
                <div><label class="block text-xs text-zinc-500 mb-1">时长（天）</label><input type="number" x-model="formProduct.duration_days" class="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm w-20" placeholder="30"></div>
                <button type="button" @click="createProduct()" class="rounded-lg bg-emerald-600 text-white px-4 py-1.5 text-sm font-medium hover:bg-emerald-700">添加</button>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden shadow-sm">
            <div class="px-5 py-3 border-b border-zinc-100">
                <h3 class="font-medium text-zinc-800">我的产品</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 text-zinc-600">
                        <tr>
                            <th class="text-left py-3 px-4">ID</th>
                            <th class="text-left py-3 px-4">名称</th>
                            <th class="text-left py-3 px-4">价格</th>
                            <th class="text-left py-3 px-4">时长</th>
                            <th class="text-left py-3 px-4">状态</th>
                            <th class="text-left py-3 px-4">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="p in products" :key="p.id">
                            <tr class="border-t border-zinc-100 hover:bg-zinc-50/50">
                                <td class="py-3 px-4" x-text="p.id"></td>
                                <td class="py-3 px-4" x-text="p.name"></td>
                                <td class="py-3 px-4" x-text="(p.price_cents||0) + ' 分'"></td>
                                <td class="py-3 px-4" x-text="(p.duration_days||0) + ' 天'"></td>
                                <td class="py-3 px-4" x-text="p.status || 'active'"></td>
                                <td class="py-3 px-4 flex gap-2">
                                    <button type="button" @click="editProduct(p)" class="text-emerald-600 hover:underline text-xs">编辑</button>
                                    <button type="button" @click="deleteProduct(p.id)" class="text-red-600 hover:underline text-xs">删除</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p x-show="products.length === 0 && tab === 'products' && !loading" class="py-6 text-center text-zinc-500 text-sm">暂无产品，请从上方添加。</p>
        </div>
    </div>

    {{-- 用户管理：在 B 站购买过本分销商产品的用户 --}}
    <div x-show="tab === 'users'" class="bg-white rounded-xl border border-zinc-200 overflow-hidden shadow-sm">
        <div class="px-5 py-3 border-b border-zinc-100">
            <h3 class="font-medium text-zinc-800">用户管理</h3>
            <p class="text-xs text-zinc-500 mt-0.5">本站注册用户，订单数为购买您产品的订单数。可编辑或删除。</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 text-zinc-600">
                    <tr>
                        <th class="text-left py-3 px-4">ID</th>
                        <th class="text-left py-3 px-4">昵称</th>
                        <th class="text-left py-3 px-4">邮箱</th>
                        <th class="text-left py-3 px-4">订单数</th>
                        <th class="text-left py-3 px-4">注册时间</th>
                        <th class="text-left py-3 px-4">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="u in users" :key="u.id">
                        <tr class="border-t border-zinc-100 hover:bg-zinc-50/50">
                            <td class="py-3 px-4" x-text="u.id"></td>
                            <td class="py-3 px-4" x-text="u.name"></td>
                            <td class="py-3 px-4" x-text="u.email"></td>
                            <td class="py-3 px-4" x-text="u.orders_count ?? 0"></td>
                            <td class="py-3 px-4" x-text="u.created_at ? new Date(u.created_at).toLocaleString() : '-'"></td>
                            <td class="py-3 px-4 flex gap-2">
                                <button type="button" @click="editUser(u)" class="text-emerald-600 hover:underline text-xs">编辑</button>
                                <button type="button" @click="deleteUser(u.id)" class="text-red-600 hover:underline text-xs">删除</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <p x-show="users.length === 0 && tab === 'users' && !loading" class="py-6 text-center text-zinc-500 text-sm">暂无用户</p>
    </div>

    {{-- 订单列表 --}}
    <div x-show="tab === 'orders'" class="bg-white rounded-xl border border-zinc-200 overflow-hidden shadow-sm">
        <div class="px-5 py-3 border-b border-zinc-100">
            <h3 class="font-medium text-zinc-800">订单列表</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 text-zinc-600">
                    <tr>
                        <th class="text-left py-3 px-4">业务订单号</th>
                        <th class="text-left py-3 px-4">用户</th>
                        <th class="text-left py-3 px-4">产品</th>
                        <th class="text-left py-3 px-4">金额</th>
                        <th class="text-left py-3 px-4">状态</th>
                        <th class="text-left py-3 px-4">到期</th>
                        <th class="text-left py-3 px-4">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="o in orders" :key="o.id">
                        <tr class="border-t border-zinc-100 hover:bg-zinc-50/50">
                            <td class="py-3 px-4 font-mono text-xs" :title="o.biz_order_no || ''" x-text="shortBizOrderNo(o.biz_order_no)"></td>
                            <td class="py-3 px-4 max-w-[200px] truncate" :title="o.user ? o.user.email : ''"><span x-text="o.user ? (o.user.name + ' / ' + o.user.email) : '-'"></span></td>
                            <td class="py-3 px-4" x-text="o.reseller_product ? o.reseller_product.name : '-'"></td>
                            <td class="py-3 px-4" x-text="o.amount_cents ? (o.amount_cents/100 + ' 元') : '-'"></td>
                            <td class="py-3 px-4" x-text="o.status === 'paid' ? '已支付' : (o.status === 'pending' ? '待支付' : o.status)"></td>
                            <td class="py-3 px-4 whitespace-nowrap" x-text="o.expires_at ? new Date(o.expires_at).toLocaleString() : '-'"></td>
                            <td class="py-3 px-4">
                                <button type="button" class="text-emerald-700 hover:underline text-xs" @click="orderDetailId = orderDetailId === o.id ? null : o.id" x-text="orderDetailId === o.id ? '收起' : '详情'"></button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div x-show="orderDetailId && tab === 'orders'" class="border-t border-zinc-100 bg-zinc-50 px-4 py-3 text-sm">
            <template x-if="selectedOrderRow()">
                <dl class="grid gap-2 sm:grid-cols-2">
                    <div class="sm:col-span-2"><dt class="text-zinc-500">完整业务单号</dt><dd class="font-mono text-xs break-all" x-text="selectedOrderRow().biz_order_no || '—'"></dd></div>
                    <div><dt class="text-zinc-500">B 内部 ID</dt><dd class="font-mono" x-text="'#' + selectedOrderRow().id"></dd></div>
                    <div><dt class="text-zinc-500">A 内部订单 ID</dt><dd class="font-mono" x-text="selectedOrderRow().a_order_id ? ('#' + selectedOrderRow().a_order_id) : '—'"></dd></div>
                    <div><dt class="text-zinc-500">开通</dt><dd x-text="selectedOrderRow().activated_at ? new Date(selectedOrderRow().activated_at).toLocaleString() : '—'"></dd></div>
                    <div><dt class="text-zinc-500">最后续费</dt><dd x-text="selectedOrderRow().last_renewed_at ? new Date(selectedOrderRow().last_renewed_at).toLocaleString() : '—'"></dd></div>
                    <div><dt class="text-zinc-500">下单时间</dt><dd x-text="selectedOrderRow().created_at ? new Date(selectedOrderRow().created_at).toLocaleString() : '—'"></dd></div>
                </dl>
            </template>
        </div>
        <p x-show="orders.length === 0 && tab === 'orders' && !loading" class="py-6 text-center text-zinc-500 text-sm">暂无订单</p>
    </div>
</div>

@push('scripts')
<script>
function resellerDashboard() {
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
        tab: 'me',
        loading: false,
        me: {},
        stats: {},
        apiKeys: [],
        aProducts: [],
        products: [],
        users: [],
        orders: [],
        orderDetailId: null,
        formProduct: { source_product_id: '', name: '', price_cents: '9900', duration_days: '30' },
        init() {
            if (!localStorage.getItem('reseller_token')) { window.location.href = '/reseller/login'; return; }
            api('reseller/me').then(d => { this.me = d || {}; }).catch(e => { if (e.message) alert(e.message); });
            api('reseller/stats').then(d => { this.stats = d || {}; }).catch(() => {});
        },
        logout() {
            localStorage.removeItem('reseller_token');
            localStorage.removeItem('reseller_name');
            window.location.href = '/reseller/login';
        },
        loadApiKeys() { api('reseller/me/api_keys').then(d => { this.apiKeys = d || []; }).catch(() => {}); },
        loadAProducts() { api('reseller/a_products').then(d => { this.aProducts = d || []; }).catch(() => {}); },
        loadProducts() { api('reseller/products').then(d => { this.products = d || []; }).catch(() => {}); },
        loadUsers() { api('reseller/users').then(d => { this.users = d || []; }).catch(() => {}); },
        loadOrders() { api('reseller/orders').then(d => { this.orders = d || []; }).catch(() => {}); },
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
        async createProduct() {
            const d = this.formProduct;
            if (!d.source_product_id || !d.name || !d.price_cents) { alert('请选择基础产品并填写名称、价格'); return; }
            try {
                await api('reseller/products', { method: 'POST', body: JSON.stringify({
                    source_product_id: parseInt(d.source_product_id),
                    name: d.name,
                    price_cents: parseInt(d.price_cents),
                    duration_days: parseInt(d.duration_days) || 30
                }) });
                this.formProduct = { source_product_id: '', name: '', price_cents: '9900', duration_days: '30' };
                this.loadProducts();
            } catch (e) { alert(e.message); }
        },
        editProduct(p) {
            const name = prompt('名称', p.name); if (name == null) return;
            const price = prompt('价格（分）', p.price_cents); if (price == null) return;
            const days = prompt('时长（天）', p.duration_days); if (days == null) return;
            api('reseller/products/' + p.id, { method: 'PUT', body: JSON.stringify({ name, price_cents: parseInt(price), duration_days: parseInt(days) }) })
                .then(() => this.loadProducts()).catch(e => alert(e.message));
        },
        async deleteProduct(id) {
            if (!confirm('确定删除该产品？')) return;
            try { await api('reseller/products/' + id, { method: 'DELETE' }); this.loadProducts(); } catch (e) { alert(e.message); }
        },
        editUser(u) {
            const name = prompt('昵称', u.name); if (name == null) return;
            const email = prompt('邮箱', u.email); if (email == null) return;
            api('reseller/users/' + u.id, { method: 'PUT', body: JSON.stringify({ name, email }) })
                .then(() => this.loadUsers()).catch(e => alert(e.message));
        },
        async deleteUser(id) {
            if (!confirm('确定删除该用户？其订单数据将一并删除。')) return;
            try { await api('reseller/users/' + id, { method: 'DELETE' }); this.loadUsers(); } catch (e) { alert(e.message); }
        },
    };
}
</script>
@endpush
@endsection
