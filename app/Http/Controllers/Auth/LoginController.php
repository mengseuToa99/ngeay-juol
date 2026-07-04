<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Enums\UserStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectUser(Auth::user());
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginField = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $attemptCredentials = [
            $loginField => $credentials['login'],
            'password' => $credentials['password'],
            'status' => UserStatus::Active->value,
        ];

        if (Auth::attempt($attemptCredentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return $this->redirectUser(Auth::user());
        }

        throw ValidationException::withMessages([
            'login' => [__('Invalid login credentials or inactive account.')],
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    protected function redirectUser($user)
    {
        if ($user->isPlatformStaff()) {
            return redirect()->intended(route('filament.admin.pages.dashboard'));
        }

        if ($user->hasAnyRole(['landlord', 'landlord_manager'])) {
            return redirect()->intended(route('filament.landlord.pages.dashboard'));
        }

        if ($user->hasRole('tenant')) {
            return redirect()->intended(route('portal.dashboard'));
        }

        return redirect()->intended('/');
    }
}
