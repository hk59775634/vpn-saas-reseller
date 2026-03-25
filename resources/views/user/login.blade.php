@extends('layouts.user')

@section('title', '登录')

@section('content')
<div class="mx-auto max-w-md">
    <section class="page-section">
        <h1 class="page-title">登录</h1>
        <p class="page-desc">使用您的账号登录以购买与管理服务。</p>
    </section>
    <div class="console-card p-8 shadow-sm">
        <form method="POST" action="{{ url('/login') }}" class="space-y-5">
            @csrf
            <div>
                <label for="email" class="form-label">邮箱</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                    class="console-input-field @error('email') border-red-500 @enderror">
                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="form-label">密码</label>
                <input id="password" type="password" name="password" required
                    class="console-input-field @error('password') border-red-500 @enderror">
            </div>
            <div class="flex items-center">
                <input id="remember" type="checkbox" name="remember" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                <label for="remember" class="ml-2 text-sm text-slate-600">记住我</label>
            </div>
            <button type="submit" class="console-btn-primary w-full">登录</button>
        </form>
        <p class="mt-6 text-center text-sm text-slate-500">还没有账号？ <a href="{{ route('register') }}" class="console-link font-medium">注册</a></p>
    </div>
</div>
@endsection
