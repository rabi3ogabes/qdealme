<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class PreferencesController extends Controller
{
    public function digits(Request $request): RedirectResponse
    {
        $data = $request->validate(['digits' => ['required', 'in:latin,arabic']]);

        if ($request->user()) {
            $request->user()->update(['digits' => $data['digits']]);
        }

        Cookie::queue(Cookie::make('digits', $data['digits'], 60 * 24 * 365, '/', null, null, false, false, 'lax'));

        return back();
    }
}
