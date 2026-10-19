<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        return view('account.show', [
            'seo' => $this->seo()->title(__('account.title'))->description(__('account.title'))->noindex(),
            'user' => $request->user(),
            'cities' => current_country()->cities()->active()->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'locale' => ['required', 'in:ar,en'],
            'digits' => ['required', 'in:latin,arabic'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ]);

        $data['marketing_opt_in'] = (bool) ($data['marketing_opt_in'] ?? false);

        $request->user()->update($data);

        Cookie::queue(Cookie::make('digits', $data['digits'], 60 * 24 * 365, '/', null, null, false, false, 'lax'));

        return redirect()->route('account', ['locale' => $data['locale']])->with('status', __('account.updated'));
    }

    public function saved(Request $request): View
    {
        $user = $request->user();

        return view('account.saved', [
            'seo' => $this->seo()->title(__('account.saved'))->description(__('account.saved'))->noindex(),
            'favorites' => $user->favorites()->public()->with(['retailer', 'cover'])->orderByDesc('favorites.created_at')->get(),
            'follows' => $user->follows()->active()->orderBy('name_en')->get(),
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('offers.index')->with('status', __('account.deleted'));
    }
}
