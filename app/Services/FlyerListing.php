<?php

namespace App\Services;

use App\Enums\RetailerType;
use App\Models\City;
use App\Models\Flyer;
use App\Models\Retailer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Builds the city offers listing from request filters. Shared by the full
 * page and the "load more" fragment so the two can never drift apart.
 */
class FlyerListing
{
    public const SORTS = ['featured', 'latest', 'ending'];

    public function __construct(
        public readonly City $city,
        public readonly ?string $type,
        public readonly string $sort,
        public readonly ?string $store,
    ) {}

    public static function fromRequest(Request $request, City $city): self
    {
        $type = $request->query('type');
        $sort = $request->query('sort', 'featured');

        return new self(
            city: $city,
            type: array_key_exists((string) $type, RetailerType::filterGroups()) ? $type : null,
            sort: in_array($sort, self::SORTS, true) ? $sort : 'featured',
            store: $request->filled('store') ? (string) $request->query('store') : null,
        );
    }

    public function query(): Builder
    {
        $query = Flyer::query()
            ->published()
            ->forCity($this->city)
            ->with(['retailer', 'cover']);

        if ($this->type !== null) {
            $query->withRetailerType(RetailerType::filterGroups()[$this->type]);
        }

        if ($this->store !== null) {
            $query->whereHas('retailer', fn (Builder $q) => $q->where('slug', $this->store));
        }

        return match ($this->sort) {
            'latest' => $query->latest(),
            'ending' => $query->endingSoon(),
            default => $query->featured(),
        };
    }

    public function paginate(): LengthAwarePaginator
    {
        return $this->query()
            ->paginate(config('qdealme.flyers_per_page'))
            ->withQueryString();
    }

    /**
     * Retailers that currently have at least one published flyer in the city,
     * ordered so the ones with the most live flyers lead the logo strip.
     */
    public function retailersStrip(): Collection
    {
        return Retailer::query()
            ->active()
            ->whereHas('flyers', fn (Builder $q) => $q->published()->forCity($this->city))
            ->withCount(['flyers as live_flyers_count' => fn (Builder $q) => $q->published()->forCity($this->city)])
            ->orderByDesc('live_flyers_count')
            ->orderBy('sort')
            ->orderBy('name_en')
            ->get();
    }

    /**
     * Number of live flyers per quick-filter group.
     *
     * @return array<string, int>
     */
    public function typeCounts(): array
    {
        $base = Flyer::query()->published()->forCity($this->city);
        $counts = ['all' => (clone $base)->count()];

        foreach (RetailerType::filterGroups() as $key => $types) {
            $count = (clone $base)->withRetailerType($types)->count();

            if ($count > 0) {
                $counts[$key] = $count;
            }
        }

        return $counts;
    }

    public function filters(): array
    {
        return array_filter([
            'type' => $this->type,
            'sort' => $this->sort === 'featured' ? null : $this->sort,
            'store' => $this->store,
        ]);
    }
}
