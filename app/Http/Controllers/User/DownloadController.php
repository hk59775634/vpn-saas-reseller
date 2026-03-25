<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DownloadController extends Controller
{
    /**
     * 客户端下载页：仅提供各平台 VPN 客户端官方入口；连接配置见「已购产品」详情。
     */
    public function index(): View
    {
        return view('user.downloads', [
            'clients' => config('vpn_clients'),
        ]);
    }
}
