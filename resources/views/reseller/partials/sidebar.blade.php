@php($route = request()->route()?->getName())

<a href="{{ route('reseller.dashboard') }}"
   class="console-sidebar-link {{ $route === 'reseller.dashboard' ? 'active' : '' }}">概览</a>
<a href="{{ route('reseller.products') }}"
   class="console-sidebar-link {{ $route === 'reseller.products' ? 'active' : '' }}">产品管理</a>
<a href="{{ route('reseller.users') }}"
   class="console-sidebar-link {{ $route === 'reseller.users' ? 'active' : '' }}">用户管理</a>
<a href="{{ route('reseller.orders') }}"
   class="console-sidebar-link {{ $route === 'reseller.orders' ? 'active' : '' }}">订单列表</a>
<a href="{{ route('reseller.subscriptions') }}"
   class="console-sidebar-link {{ $route === 'reseller.subscriptions' ? 'active' : '' }}">已购产品</a>
<a href="{{ route('reseller.api_keys') }}"
   class="console-sidebar-link {{ $route === 'reseller.api_keys' ? 'active' : '' }}">API Keys</a>
<a href="{{ route('reseller.payment') }}"
   class="console-sidebar-link {{ $route === 'reseller.payment' ? 'active' : '' }}">支付设置</a>
<a href="{{ route('reseller.site_settings') }}"
   class="console-sidebar-link {{ $route === 'reseller.site_settings' ? 'active' : '' }}">站点信息</a>
<a href="{{ route('reseller.runtime_settings') }}"
   class="console-sidebar-link {{ $route === 'reseller.runtime_settings' ? 'active' : '' }}">安全与限流</a>
