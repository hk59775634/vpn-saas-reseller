@extends('layouts.user')

@section('title', '下载')

@section('content')
<section class="page-section">
    <h1 class="page-title">客户端下载</h1>
    <p class="page-desc">
        在此下载 <strong>WireGuard</strong> 与 <strong>Cisco Secure Client（AnyConnect）</strong> 各系统官方客户端。
        <strong>连接配置与账号信息</strong>请在
        <a href="{{ route('user.subscriptions') }}" class="console-link font-medium">已购产品</a>
        中查看（含 WireGuard 配置文本等）。
    </p>
</section>

@php
    $wg = $clients['wireguard'] ?? [];
    $ac = $clients['anyconnect'] ?? [];
@endphp

<div class="max-w-4xl space-y-8">
    <div class="console-card p-6 shadow-sm">
        <h2 class="mb-1 text-base font-semibold text-slate-900">WireGuard 客户端</h2>
        <p class="mb-4 text-sm text-slate-600">安装后，将「已购产品」中提供的配置导入即可连接。</p>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach($wg as $row)
                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                    <div class="mb-2 flex items-baseline justify-between gap-2">
                        <span class="font-semibold text-slate-900">{{ $row['platform'] }}</span>
                        @if(!empty($row['hint']))
                            <span class="text-xs text-slate-500">{{ $row['hint'] }}</span>
                        @endif
                    </div>
                    <ul class="space-y-2 text-sm">
                        @foreach($row['links'] ?? [] as $link)
                            <li>
                                <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" class="console-link font-medium">
                                    {{ $link['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>

    <div class="console-card p-6 shadow-sm">
        <h2 class="mb-1 text-base font-semibold text-slate-900">Cisco Secure Client（SSL VPN / 原 AnyConnect）</h2>
        <p class="mb-4 text-sm text-slate-600">
            用于连接 SSL VPN；<strong>服务器地址、账号</strong>以「已购产品」或服务商说明为准。桌面安装包部分环境由管理员单独分发，亦可从 Cisco 官网获取。
        </p>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach($ac as $row)
                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                    <div class="mb-2 flex items-baseline justify-between gap-2">
                        <span class="font-semibold text-slate-900">{{ $row['platform'] }}</span>
                        @if(!empty($row['hint']))
                            <span class="text-xs text-slate-500">{{ $row['hint'] }}</span>
                        @endif
                    </div>
                    <ul class="space-y-2 text-sm">
                        @foreach($row['links'] ?? [] as $link)
                            <li>
                                <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" class="console-link font-medium">
                                    {{ $link['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>

    <div class="console-alert-info text-sm">
        <p class="font-medium text-slate-900">提示</p>
        <p class="mt-1 text-slate-600">外站下载链接由第三方维护，请以厂商页面为准。若无法访问应用商店，请使用对应系统的官方应用市场搜索 「WireGuard」或 「Cisco Secure Client」。</p>
    </div>
</div>
@endsection
