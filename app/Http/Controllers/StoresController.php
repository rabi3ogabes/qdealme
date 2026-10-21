<?php

namespace App\Http\Controllers;

use App\Enums\RetailerType;
use App\Models\Flyer;
use App\Models\Retailer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class StoresController extends Controller
{
    public function index(Request $request): View
    {
        $city = current_city();

        $retailers = Retailer::query()
            ->active()
            ->withCount(['flyers as live_flyers_count' => fn (Builder $q) => $q->published()->forCity($city)])
            ->orderByDesc('live_flyers_count')
            ->orderBy('name_en')
            ->get()
            ->groupBy(fn (Retailer $r) => $this->groupKey($r->type));

        $seo = $this->seo()
            ->title(__('seo.stores_title', ['city' => $city->name]))
            ->description(__('seo.stores_description', ['city' => $city->name, 'brand' => brand()]))
            ->breadcrumbs([
                __('nav.offers') => route('offers.index'),
                __('nav.stores') => route('stores.index'),
            ]);

        return view('stores.index', [
            'seo' => $seo,
            'groups' => $retailers,
            'city' => $city,
        ]);
    }

    public function show(Request $request, Retailer $retailer): View
    {
        abort_unless($retailer->is_active, 404);

        $city = current_city();
        $today = local_today($city);

        $current = Flyer::query()->published()->forCity($city)
            ->where('retailer_id', $retailer->id)->with(['retailer', 'cover'])->featured()->get();

        $upcoming = Flyer::query()->upcoming()->forCity($city)
            ->where('retailer_id', $retailer->id)->with(['retailer', 'cover'])->orderBy('valid_from')->get();

        $expiredPreview = Flyer::query()->where('status', 'expired')->forCity($city)
            ->where('retailer_id', $retailer->id)->with(['retailer', 'cover'])->orderByDesc('valid_until')->limit(8)->get();

        $expiredCount = Flyer::query()->where('status', 'expired')->forCity($city)
            ->where('retailer_id', $retailer->id)->count();

        $offers = $retailer->offers()->live()->with(['flyer.retailer', 'page', 'category'])
            ->orderByDesc('old_price')->limit(8)->get();

        $branches = $retailer->branches()->where('city_id', $city->id)->where('is_active', true)->get();
        $isFollowing = $request->user()?->follows()->whereKey($retailer->id)->exists() ?? false;

        $seo = $this->seo()
            ->title(__('seo.store_title', ['store' => $retailer->name, 'city' => $city->name]))
            ->description(__('seo.store_description', [
                'store' => $retailer->name,
                'city' => $city->name,
                'count' => fmt_number($current->count()),
            ]))
            ->image($retailer->logoUrl())
            ->breadcrumbs([
                __('nav.offers') => route('offers.index'),
                __('nav.stores') => route('stores.index'),
                $retailer->name => route('stores.show', $retailer),
            ])
            ->jsonLd(array_filter([
                '@type' => 'Store',
                'name' => $retailer->name,
                'url' => route('stores.show', $retailer),
                'image' => $retailer->logoUrl(),
                'sameAs' => $retailer->website_url,
                'address' => $branches->map(fn ($b) => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $b->address,
                    'addressLocality' => $city->name,
                    'addressCountry' => $city->country->code,
                ])->all(),
            ]));

        return view('stores.show', [
            'seo' => $seo,
            'retailer' => $retailer,
            'city' => $city,
            'current' => $current,
            'upcoming' => $upcoming,
            'expiredPreview' => $expiredPreview,
            'expiredCount' => $expiredCount,
            'offers' => $offers,
            'branches' => $branches,
            'isFollowing' => $isFollowing,
            'today' => $today,
        ]);
    }

    public function archive(Request $request, Retailer $retailer): View
    {
        $city = current_city();

        $flyers = Flyer::query()->where('status', 'expired')->forCity($city)
            ->where('retailer_id', $retailer->id)
            ->with(['retailer', 'cover'])
            ->orderByDesc('valid_until')
            ->paginate(config('qdealme.archive_per_page'))
            ->withQueryString();

        $seo = $this->seo()
            ->title(__('seo.store_archive_title', ['store' => $retailer->name, 'city' => $city->name]))
            ->description(__('seo.store_archive_description', ['store' => $retailer->name]))
            ->noindex();

        return view('stores.archive', [
            'seo' => $seo,
            'retailer' => $retailer,
            'flyers' => $flyers,
        ]);
    }

    private function groupKey(RetailerType $type): string
    {
        foreach (RetailerType::filterGroups() as $key => $types) {
            if (in_array($type, $types, true)) {
                return $key;
            }
        }

        return 'other';
    }
}
