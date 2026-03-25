<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function settings(): View
    {
        return view('reseller.settings');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $envUser = env('RESELLER_ADMIN_USERNAME', 'admin');
        $envHash = env('RESELLER_ADMIN_PASSWORD_HASH');

        if ($envHash && !Hash::check($data['current_password'], $envHash)) {
            return back()->withErrors(['current_password' => '当前密码不正确'])->withInput();
        }

        $newHash = Hash::make($data['new_password']);

        $path = base_path('.env');
        if (File::exists($path)) {
            $env = File::get($path);
            $key = 'RESELLER_ADMIN_PASSWORD_HASH';
            $pattern = "/^{$key}=.*$/m";
            $line = $key . '=' . $newHash;
            if (preg_match($pattern, $env)) {
                $env = preg_replace($pattern, $line, $env);
            } else {
                $env .= PHP_EOL . $line;
            }
            File::put($path, $env);
        }

        return back()->with('message', '密码已更新，请使用新密码重新登录。');
    }
}
