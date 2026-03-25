@extends('layouts.reseller')

@section('title', '用户管理')
@section('header_title', '用户管理')

@section('sidebar')
    @include('reseller.partials.sidebar')
@endsection

@section('content')
<div x-data="resellerUsers()" x-init="init()" x-cloak class="console-table-wrap">
    <div class="console-card-header">
        <div>
            <h3 class="console-card-title">用户管理</h3>
            <p class="mt-0.5 text-xs text-slate-500">本站注册用户；订单数为购买您名下产品的订单数。支持编辑与删除。</p>
        </div>
    </div>
    <div class="console-filter-bar">
        <input type="text" placeholder="搜索用户..." class="console-filter-input max-w-xs" disabled aria-label="搜索（占位）">
    </div>
    <div class="overflow-x-auto">
        <table class="console-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>昵称</th>
                    <th>邮箱</th>
                    <th>订单数</th>
                    <th>注册时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="u in users" :key="u.id">
                    <tr>
                        <td x-text="u.id"></td>
                        <td x-text="u.name"></td>
                        <td x-text="u.email"></td>
                        <td x-text="u.orders_count ?? 0"></td>
                        <td x-text="u.created_at ? new Date(u.created_at).toLocaleString() : '-'"></td>
                        <td class="flex gap-2">
                            <button type="button" @click="openEdit(u)" class="console-link text-xs font-medium">编辑</button>
                            <button type="button" @click="deleteUser(u.id)" class="text-red-600 hover:underline text-xs font-medium">删除</button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    <p x-show="users.length === 0 && !loading" class="py-6 text-center text-sm text-slate-500">暂无用户</p>

    {{-- 编辑用户 --}}
    <div
        x-show="showEdit"
        x-cloak
        class="console-modal-backdrop"
        style="display: none;"
        @keydown.escape.window="closeEdit()"
    >
        <div class="console-modal-panel max-w-md" role="dialog" aria-modal="true">
            <div class="console-modal-header flex items-start justify-between gap-3">
                <div>
                    <h3 class="console-modal-title">编辑用户</h3>
                    <p class="mt-1 font-mono text-xs text-slate-500" x-text="editing?.email"></p>
                </div>
                <button type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" @click="closeEdit()" aria-label="关闭">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="console-modal-body space-y-4 text-sm">
                <div>
                    <label class="console-modal-label">昵称</label>
                    <input type="text" class="console-modal-input" x-model="form.name" placeholder="用户昵称">
                </div>
                <div>
                    <label class="console-modal-label">邮箱</label>
                    <input type="email" class="console-modal-input" x-model="form.email" placeholder="user@example.com">
                </div>
                <div>
                    <label class="console-modal-label">重置密码（可选）</label>
                    <input type="password" class="console-modal-input" x-model="form.password" placeholder="留空则不修改密码" autocomplete="new-password">
                    <p class="mt-1.5 text-[11px] text-slate-500">仅在需要重置时填写；留空保持原密码。</p>
                </div>
            </div>
            <div class="console-modal-footer">
                <button type="button" class="console-btn-secondary" @click="closeEdit()">取消</button>
                <button type="button" class="console-btn-primary" @click="saveEdit()">保存</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function resellerUsers() {
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
        users: [],
        showEdit: false,
        editing: null,
        form: {
            name: '',
            email: '',
            password: '',
        },
        init() {
            if (!localStorage.getItem('reseller_token')) { window.location.href = '/reseller/login'; return; }
            this.loadUsers();
        },
        loadUsers() {
            api('reseller/users')
                .then(d => { this.users = d || []; })
                .catch(() => {});
        },
        openEdit(u) {
            this.editing = u;
            this.form.name = u.name || '';
            this.form.email = u.email || '';
            this.form.password = '';
            this.showEdit = true;
        },
        closeEdit() {
            this.showEdit = false;
            this.editing = null;
            this.form.password = '';
        },
        async saveEdit() {
            if (!this.editing) return;
            const payload = {
                name: this.form.name,
                email: this.form.email,
            };
            if (this.form.password && this.form.password.length > 0) {
                payload.password = this.form.password;
            }
            try {
                await api('reseller/users/' + this.editing.id, {
                    method: 'PUT',
                    body: JSON.stringify(payload),
                });
                this.closeEdit();
                this.loadUsers();
            } catch (e) {
                alert(e.message || '保存失败');
            }
        },
        async deleteUser(id) {
            if (!confirm('确定删除该用户？其订单数据将一并删除。')) return;
            try {
                await api('reseller/users/' + id, { method: 'DELETE' });
                this.loadUsers();
            } catch (e) {
                alert(e.message);
            }
        },
    };
}
</script>
@endpush
@endsection

