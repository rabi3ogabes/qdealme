<?php

namespace App\Models;

use App\Enums\FlyerStatus;
use App\Exceptions\UnlicensedSourceException;
use App\Models\Concerns\HasLocalizedAttributes;
use App\Models\Concerns\Searchable;
use App\Support\Countdown;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Flyer extends Model
{
    use HasFactory;
    use HasLocalizedAttributes;
    use Searchable;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => FlyerStatus::class,
            'valid_from' => 'date',
            'valid_until' => 'date',
            'sponsored_until' => 'date',
            'is_sponsored' => 'boolean',
            'upload_files' => 'array',
            'published_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Lawful sourcing is enforced at the model layer: a flyer can never be
        // persisted unless it points at a source whose licence is approved.
        static::saving(function (Flyer $flyer): void {
            if ($flyer->isDirty('flyer_source_id') || ! $flyer->exists) {
                $source = FlyerSource::query()->find($flyer->flyer_source_id);

                if ($source === null || ! $source->isApproved()) {
                    throw UnlicensedSourceException::forSource($flyer->flyer_source_id);
                }
            }

            if (blank($flyer->slug)) {
                $flyer->slug = Str::slug($flyer->title_en) ?: 'flyer';
            }
        });
    }

    /* ----------------------------------------------------------------------
     | Relationships
     | -------------------------------------------------------------------- */

    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(FlyerSource::class, 'flyer_source_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(FlyerPage::class)->orderBy('number');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(FlyerPage::class, 'cover_page_id');
    }

    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class, 'flyer_city');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'flyer_branch');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withPivot('created_at');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /* ----------------------------------------------------------------------
     | Scopes
     | -------------------------------------------------------------------- */

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', FlyerStatus::Published->value);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->whereIn('status', [FlyerStatus::Published->value, FlyerStatus::Expired->value]);
    }

    public function scopeForCity(Builder $query, City $city): Builder
    {
        return $query->whereHas('cities', fn (Builder $q) => $q->whereKey($city->id));
    }

    public function scopeScheduledToStart(Builder $query, CarbonInterface $today): Builder
    {
        return $query->where('status', FlyerStatus::Scheduled->value)
            ->whereDate('valid_from', '<=', $today->toDateString());
    }

    public function scopeDueToExpire(Builder $query, CarbonInterface $today): Builder
    {
        return $query->where('status', FlyerStatus::Published->value)
            ->whereDate('valid_until', '<', $today->toDateString());
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', FlyerStatus::Scheduled->value);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->orderByDesc('is_sponsored')->orderByDesc('published_at');
    }

    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function scopeEndingSoon(Builder $query): Builder
    {
        return $query->orderBy('valid_until')->orderByDesc('published_at');
    }

    public function scopeWithRetailerType(Builder $query, array $types): Builder
    {
        return $query->whereHas('retailer', fn (Builder $q) => $q->whereIn('type', array_map(fn ($t) => $t->value, $types)));
    }

    /* ----------------------------------------------------------------------
     | Presentation helpers
     | -------------------------------------------------------------------- */

    public function getTitleAttribute(): ?string
    {
        return $this->localized('title');
    }

    public function countdown(?CarbonInterface $today = null): Countdown
    {
        return Countdown::for($this->valid_from, $this->valid_until, $today);
    }

    public function isExpired(): bool
    {
        return $this->status === FlyerStatus::Expired;
    }

    public function isPublished(): bool
    {
        return $this->status === FlyerStatus::Published;
    }

    public function isSponsoredNow(): bool
    {
        return $this->is_sponsored && ($this->sponsored_until === null || ! $this->sponsored_until->isPast());
    }

    /**
     * URL segment: "{slug}-{id}". The id at the end keeps links stable even
     * if the title changes later.
     */
    public function routeSegment(): string
    {
        return $this->slug.'-'.$this->id;
    }

    public function url(int $page = 1, ?string $locale = null): string
    {
        $params = ['retailer' => $this->retailer->slug, 'flyer' => $this->routeSegment()];

        if ($page > 1) {
            $params['page'] = $page;
        }

        if ($locale) {
            $params['locale'] = $locale;
        }

        return route('flyers.show', $params);
    }

    public function coverPage(): ?FlyerPage
    {
        return $this->cover ?? $this->pages->first();
    }

    public static function idFromSegment(string $segment): ?int
    {
        if (preg_match('/-(\d+)$/', $segment, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * @return array<int, string|null>
     */
    public function searchableValues(): array
    {
        $retailer = $this->retailer ?? Retailer::query()->find($this->retailer_id);

        return [$this->title_ar, $this->title_en, $retailer?->name_ar, $retailer?->name_en];
    }
}
