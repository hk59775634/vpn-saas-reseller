@extends('layouts.reseller')

@section('title', 'A 站配置')
@section('header_title', 'A 站配置')

@section('sidebar')
    @include('reseller.partials.sidebar')
@endsection

@section('content')
<div class="max-w-xl space-y-6">
    <div class="console-card">
        <div class="console-card-header">
            <h3 class="console-card-title">A 站连接配置</h3>
        </div>
        <div class="p-6 space-y-4">
            @if(session('message'))
                <p class="text-sm text-emerald-600">{{ session('message') }}</p>
            @endif
            @if($errors->any())
                <ul class="text-sm text-red-600 list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <form method="POST" action="{{ route('reseller.api_keys.update') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs text-zinc-600 mb-1" for="VPN_A_URL">A 站 URL</label>
                    <input type="text" id="VPN_A_URL" name="VPN_A_URL"
                           value="{{ old('VPN_A_URL', $vpn_a_url) }}"
                           class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm"
                           placeholder="https://a.ai101.eu.org">
                </div>
                <div>
                    <label class="block text-xs text-zinc-600 mb-1" for="VPN_A_RESELLER_API_KEY">A 站分销商 API Key</label>
                    <input type="text" id="VPN_A_RESELLER_API_KEY" name="VPN_A_RESELLER_API_KEY"
                           value="{{ old('VPN_A_RESELLER_API_KEY', $vpn_a_key) }}"
                           class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm font-mono"
                           placeholder="rk_xxx">
                    <p class="mt-1 text-xs text-zinc-500">用于 B 站在用户支付成功后调用 A 站分销商开通接口（服务端使用，不再作为后台登录凭证）。</p>
                </div>
                <div class="pt-2">
                    <button type="submit" class="console-btn-primary">保存配置</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

