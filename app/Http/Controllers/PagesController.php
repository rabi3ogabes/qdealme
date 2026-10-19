<?php

namespace App\Http\Controllers;

use App\Enums\RetailerType;
use App\Models\ContactMessage;
use App\Models\Lead;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PagesController extends Controller
{
    public function about(): View
    {
        return view('pages.about', [
            'seo' => $this->seo()->title(__('pages.about.title'))->description(__('pages.about.meta')),
        ]);
    }

    public function privacy(): View
    {
        return view('pages.privacy', [
            'seo' => $this->seo()->title(__('pages.privacy.title'))->description(__('pages.privacy.meta')),
        ]);
    }

    public function terms(): View
    {
        return view('pages.terms', [
            'seo' => $this->seo()->title(__('pages.terms.title'))->description(__('pages.terms.meta')),
        ]);
    }

    public function contact(): View
    {
        return view('pages.contact', [
            'seo' => $this->seo()->title(__('pages.contact.title'))->description(__('pages.contact.meta')),
        ]);
    }

    public function sendContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            'website' => ['prohibited'], // honeypot
        ]);

        unset($data['website']);

        ContactMessage::query()->create($data + ['locale' => app()->getLocale()]);

        return redirect()->route('pages.contact')->with('status', __('pages.contact.sent'));
    }

    public function listStore(): View
    {
        return view('pages.list-store', [
            'seo' => $this->seo()->title(__('pages.list_store.title'))->description(__('pages.list_store.meta')),
            'types' => RetailerType::cases(),
            'cities' => current_country()->cities()->active()->get(),
        ]);
    }

    public function storeLead(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:160'],
            'contact_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'retailer_type' => ['nullable', 'string', 'in:'.implode(',', array_map(fn ($t) => $t->value, RetailerType::cases()))],
            'message' => ['nullable', 'string', 'max:2000'],
            'website' => ['prohibited'], // honeypot
        ]);

        unset($data['website']);

        Lead::query()->create($data + ['locale' => app()->getLocale()]);

        return redirect()->route('pages.list-store')->with('status', __('pages.list_store.sent'));
    }
}
