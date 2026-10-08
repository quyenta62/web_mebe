<?php

namespace App\Http\Controllers;

use App\Support\AdminCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function showLogin(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('admin_username')) {
            return redirect()->route('groups.index');
        }

        return view('auth.login', ['configured' => AdminCredentials::configured()]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $throttleKey = 'admin-login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            return back()->withErrors([
                'username' => 'Đăng nhập sai quá nhiều lần. Thử lại sau '.RateLimiter::availableIn($throttleKey).' giây.',
            ])->onlyInput('username');
        }

        if (! AdminCredentials::configured()) {
            return back()->withErrors([
                'username' => 'Chưa cấu hình ADMIN_USERNAME / ADMIN_PASSWORD trong file .env.',
            ])->onlyInput('username');
        }

        if (! AdminCredentials::check($credentials['username'], $credentials['password'])) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            return back()->withErrors(['username' => 'Sai tài khoản hoặc mật khẩu.'])->onlyInput('username');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $request->session()->put('admin_username', $credentials['username']);

        return redirect()->intended(route('groups.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
