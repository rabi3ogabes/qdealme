<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login', [
            'seo' => $this->seo()->title(__('auth.login'))->description(__('auth.login_meta'))->noindex(),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('offers.index'))->with('status', __('auth.welcome_back', ['name' => $request->user()->name]));
    }

    public function showRegister(): View
    {
        return view('auth.register', [
            'seo' => $this->seo()->title(__('auth.register'))->description(__('auth.register_meta'))->noindex(),
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::Shopper,
            'locale' => app()->getLocale(),
            'city_id' => current_city()?->id,
            'marketing_opt_in' => (bool) ($data['marketing_opt_in'] ?? false),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('offers.index')->with('status', __('auth.registered', ['name' => $user->name]));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('offers.index')->with('status', __('auth.logged_out'));
    }
}
