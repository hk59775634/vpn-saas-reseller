@extends('layouts.app')

@section('title', '分销商登录')

@section('body')
<div class="flex min-h-screen items-center justify-center bg-slate-900 px-4">
    <div class="w-full max-w-md">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-bold tracking-tight text-white">{{ $siteName }}</h1>
            <p class="mt-1 text-sm text-slate-400">分销商中心 · B站</p>
        </div>
        <div class="rounded-lg border border-slate-700 bg-slate-800 p-8 shadow-xl">
            <h2 class="mb-6 text-lg font-semibold text-white">账号密码登录</h2>
            <form x-data="{ username: 'admin', password: '', error: (new URLSearchParams(window.location.search)).get('reason') === 'forbidden' ? '无权限，请重新登录' : (new URLSearchParams(window.location.search)).get('reason') === 'unauthorized' ? '登录已过期，请重新登录' : '', loading: false }"
                  @submit.prevent="
                    error = '';
                    if (!username.trim() || !password) { error = '请输入账号和密码'; return; }
                    loading = true;
                    fetch('/api/v1/reseller/auth', {
                      method: 'POST',
                      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                      body: JSON.stringify({ username: username.trim(), password: password })
                    })
                    .then(r => r.json().then(d => ({ ok: r.ok, data: d })))
                    .then(({ ok, data }) => {
                      loading = false;
                      const payload = (data && typeof data === 'object' && Object.prototype.hasOwnProperty.call(data, 'success') && Object.prototype.hasOwnProperty.call(data, 'data')) ? data.data : data;
                      if (ok && payload && payload.token) {
                        localStorage.setItem('reseller_token', payload.token);
                        if (payload.reseller && payload.reseller.name) localStorage.setItem('reseller_name', payload.reseller.name);
                        window.location.href = '/reseller';
                      } else {
                        error = data.message || '账号或密码错误';
                      }
                    })
                    .catch(e => { loading = false; error = e.message || '网络错误'; });
                  "
                  class="space-y-5">
                @csrf
                <div>
                    <label for="username" class="mb-1.5 block text-sm font-medium text-slate-300">账号</label>
                    <input type="text" id="username" name="username" x-model="username"
                           placeholder="默认：admin"
                           class="w-full rounded-md border border-slate-600 bg-slate-700 px-4 py-2.5 text-white placeholder-slate-500 focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                </div>
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-300">密码</label>
                    <input type="password" id="password" name="password" x-model="password"
                           placeholder="默认：admin123"
                           class="w-full rounded-md border border-slate-600 bg-slate-700 px-4 py-2.5 text-white placeholder-slate-500 focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                </div>
                <p x-show="error" x-text="error" class="text-sm text-red-400"></p>
                <button type="submit"
                        :disabled="loading"
                        class="w-full rounded-md bg-sky-600 py-2.5 font-medium text-white transition hover:bg-sky-500 disabled:opacity-50">
                    <span x-show="!loading">登录</span>
                    <span x-show="loading">登录中…</span>
                </button>
            </form>
            <p class="mt-4 text-xs text-slate-500">由管理员在 A 站为分销商生成 API Key 后使用。</p>
        </div>
    </div>
</div>
@endsection
