@extends('layouts.reseller')

@section('title', '站点信息')
@section('header_title', '站点信息')

@section('sidebar')
    @include('reseller.partials.sidebar')
@endsection

@section('content')
<div class="max-w-3xl space-y-6">
    @if (session('message'))
        <div class="console-alert-success mb-6">{{ session('message') }}</div>
    @endif
    @if ($errors->any())
        <div class="console-alert-error mb-6 space-y-1 text-sm">
            @foreach ($errors->all() as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('reseller.site_settings.update') }}" class="space-y-6">
        @csrf

        <div class="console-card p-6">
            <h2 class="mb-4 font-semibold text-slate-900">前台展示</h2>
            <p class="mb-4 text-xs text-slate-500">影响用户端导航标题、页脚等；未填写项使用系统默认（如 <code class="rounded bg-slate-100 px-1">APP_NAME</code>）。</p>
            <div class="space-y-4 text-sm">
                <div>
                    <label class="mb-1 block font-medium text-slate-700">站点名称</label>
                    <input type="text" name="site_name" value="{{ old('site_name', $site_name) }}"
                           class="console-filter-input w-full max-w-lg" maxlength="64" placeholder="例如：某某 VPN 服务">
                    @error('site_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block font-medium text-slate-700">站点副标题 / 一句话</label>
                    <input type="text" name="site_tagline" value="{{ old('site_tagline', $site_tagline) }}"
                           class="console-filter-input w-full max-w-lg" maxlength="255" placeholder="可选，用于首页或说明文案">
                    @error('site_tagline')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block font-medium text-slate-700">站点简介（meta description）</label>
                    <textarea name="meta_description" rows="3"
                              class="console-filter-input w-full max-w-2xl" maxlength="512"
                              placeholder="搜索引擎摘要，可留空">{{ old('meta_description', $meta_description) }}</textarea>
                    @error('meta_description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="console-card p-6">
            <h2 class="mb-4 font-semibold text-slate-900">联系与合规</h2>
            <div class="space-y-4 text-sm">
                <div>
                    <label class="mb-1 block font-medium text-slate-700">客服邮箱</label>
                    <input type="email" name="support_email" value="{{ old('support_email', $support_email) }}"
                           class="console-filter-input w-full max-w-lg" maxlength="255" placeholder="可选，显示在用户端页脚">
                    @error('support_email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block font-medium text-slate-700">ICP 备案号 / 其它说明</label>
                    <input type="text" name="icp" value="{{ old('icp', $icp) }}"
                           class="console-filter-input w-full max-w-lg" maxlength="128" placeholder="例如：京ICP备xxxxxxxx号">
                    @error('icp')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="console-btn-primary">保存设置</button>
        </div>
    </form>
</div>
@endsection
