@extends('layouts.user')

@section('title', '注册')

@section('content')
<div class="mx-auto max-w-md">
    <section class="page-section">
        <h1 class="page-title">注册</h1>
        <p class="page-desc">创建账号后即可购买套餐并管理订单。</p>
    </section>
    <div class="console-card p-8 shadow-sm">
        <form method="POST" action="{{ url('/register') }}" class="space-y-5">
            @csrf
            <div>
                <label for="name" class="form-label">昵称</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required
                    class="console-input-field @error('name') border-red-500 @enderror">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="form-label">邮箱</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required
                    class="console-input-field @error('email') border-red-500 @enderror">
                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            @if(!empty($regions) && is_array($regions))
                <div>
                    <label for="region" class="form-label">线路 / 区域</label>
                    <select id="region" name="region"
                        class="console-input-field @error('region') border-red-500 @enderror">
                        <option value="">默认（推荐）</option>
                        @foreach($regions as $r)
                            <option
                                value="{{ $r }}"
                                @if(old('region', $defaultRegion ?? '') === $r) selected @endif
                            >{{ $r }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">用于注册同步与后续开通时选择接入区域。</p>
                    @error('region')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            @endif
            <div>
                <label for="password" class="form-label">密码</label>
                <input id="password" type="password" name="password" required
                    class="console-input-field @error('password') border-red-500 @enderror">
                @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="form-label">确认密码</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required
                    class="console-input-field">
            </div>
            <button type="submit" class="console-btn-primary w-full">注册</button>
        </form>
        <p class="mt-6 text-center text-sm text-slate-500">已有账号？ <a href="{{ route('login') }}" class="console-link font-medium">登录</a></p>
    </div>
</div>
@endsection
