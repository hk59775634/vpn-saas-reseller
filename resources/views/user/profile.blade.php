@extends('layouts.user')

@section('title', '个人中心')

@section('content')
<section class="page-section">
    <h1 class="page-title">个人中心</h1>
    <p class="page-desc">管理您的账号信息与密码。</p>
</section>
<div class="max-w-md space-y-6">
    <div class="console-card p-6 shadow-sm">
        <h2 class="mb-4 text-base font-semibold text-slate-900">基本信息</h2>
        <form method="POST" action="{{ route('user.profile.update') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="name" class="form-label">昵称</label>
                <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required
                    class="console-input-field @error('name') border-red-500 @enderror">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="form-label">邮箱</label>
                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required
                    class="console-input-field @error('email') border-red-500 @enderror">
                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="console-btn-primary">保存</button>
        </form>
    </div>
    <div class="console-card p-6 shadow-sm">
        <h2 class="mb-4 text-base font-semibold text-slate-900">修改密码</h2>
        <form method="POST" action="{{ route('user.profile.password') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="current_password" class="form-label">当前密码</label>
                <input id="current_password" type="password" name="current_password" required
                    class="console-input-field @error('current_password') border-red-500 @enderror">
                @error('current_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="form-label">新密码</label>
                <input id="password" type="password" name="password" required
                    class="console-input-field @error('password') border-red-500 @enderror">
                @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="form-label">确认新密码</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required
                    class="console-input-field">
            </div>
            <button type="submit" class="console-btn-secondary">修改密码</button>
        </form>
    </div>
</div>
@endsection
