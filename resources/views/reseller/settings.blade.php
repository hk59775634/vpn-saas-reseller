@extends('layouts.reseller')

@section('title', '账户设置')
@section('header_title', '账户设置')

@section('sidebar')
    @include('reseller.partials.sidebar')
@endsection

@section('content')
<div class="max-w-xl space-y-6">
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
        <h2 class="text-lg font-semibold text-zinc-900 mb-4">修改密码</h2>
        @if(session('message'))
            <p class="mb-3 text-sm text-emerald-600">{{ session('message') }}</p>
        @endif
        @if($errors->any())
            <ul class="mb-3 text-sm text-red-600 list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
        <form method="POST" action="{{ route('reseller.settings.password') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs text-zinc-600 mb-1" for="current_password">当前密码</label>
                <input type="password" id="current_password" name="current_password"
                       class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm"
                       required>
            </div>
            <div>
                <label class="block text-xs text-zinc-600 mb-1" for="new_password">新密码</label>
                <input type="password" id="new_password" name="new_password"
                       class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm"
                       required minlength="8">
            </div>
            <div>
                <label class="block text-xs text-zinc-600 mb-1" for="new_password_confirmation">确认新密码</label>
                <input type="password" id="new_password_confirmation" name="new_password_confirmation"
                       class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm"
                       required minlength="8">
            </div>
            <div class="pt-2">
                <button type="submit" class="console-btn-primary">保存密码</button>
            </div>
        </form>
    </div>
</div>
@endsection

