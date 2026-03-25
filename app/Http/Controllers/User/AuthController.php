<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\VpnAValidateService;
use App\Support\UserLanding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private VpnAValidateService $vpnA,
    ) {
    }
    public function showLogin(): View
    {
        return view('user.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        if (Auth::guard('web')->attempt($v, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route(UserLanding::routeName()));
        }
        return back()->withErrors(['email' => '邮箱或密码错误'])->onlyInput('email');
    }

    public function showRegister(): View
    {
        $regions = $this->vpnA->getPublicRegions();
        return view('user.register', [
            'regions' => $regions,
            'defaultRegion' => (string) (config('services.vpn_a.default_region') ?? ''),
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'region' => 'nullable|string|max:64',
        ]);
        $user = User::create([
            'name' => $v['name'],
            'email' => $v['email'],
            'password' => Hash::make($v['password']),
        ]);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        // 注册成功后，同步到 A 站（失败不影响本地注册）
        try {
            $region = ($v['region'] ?? null) ?: config('services.vpn_a.default_region');
            $this->vpnA->syncUser($user->email, $user->name, $region);
        } catch (\Throwable $e) {
            // 忽略异常，避免影响用户体验
        }
        return redirect()->route(UserLanding::routeName());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('user.products');
    }
}
