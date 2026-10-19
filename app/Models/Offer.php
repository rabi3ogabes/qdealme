<?php

namespace App\Models;

use App\Enums\Verification;
use App\Models\Concerns\HasLocalizedAttributes;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Offer extends Model
{
    use HasFactory;
    use HasLocalizedAttributes;
    use Searchable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'verification' => Verification::class,
            'price' => 'decimal:2',
            'old_price' => 'decimal:2',
            'unit_price' => 'decimal:4',
            'size_value' => 'decimal:3',
            'confidence' => 'float',
            'bbox' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Offer $offer): void {
            if (blank($offer->slug)) {
                $offer->slug = Str::slug($offer->title_en) ?: 'offer';
            }

            $offer->unit_price = $offer->computeUnitPrice();
        });
    }

    public function flyer(): BelongsTo
    {
        return $this->belongsTo(Flyer::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(FlyerPage::class, 'flyer_page_id');
    }

    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification', Verification::Verified->value);
    }

    /**
     * Offers that are visible to shoppers: verified, and on a published flyer.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->verified()->whereHas('flyer', fn (Builder $q) => $q->published());
    }

    public function getTitleAttribute(): ?string
    {
        return $this->localized('title');
    }

    public function isVerified(): bool
    {
        return $this->verification === Verification::Verified;
    }

    public function saving(): ?float
    {
        if ($this->old_price === null || (float) $this->old_price <= (float) $this->price) {
            return null;
        }

        return round((float) $this->old_price - (float) $this->price, 2);
    }

    public function savingPercent(): ?int
    {
        $saving = $this->saving();

        if ($saving === null || (float) $this->old_price <= 0) {
            return null;
        }

        return (int) round($saving / (float) $this->old_price * 100);
    }

    /**
     * Price per base unit (kg, L, piece). Grams and millilitres are converted
     * to their base unit so a 500 g and a 1 kg pack compare correctly.
     */
    public function computeUnitPrice(): ?float
    {
        if ($this->size_value === null || (float) $this->size_value <= 0 || ! $this->size_unit) {
            return null;
        }

        $value = (float) $this->size_value;
        $unit = strtolower($this->size_unit);

        [$factor, $base] = match ($unit) {
            'g', 'gm', 'gram' => [1000, 'kg'],
            'kg' => [1, 'kg'],
            'ml' => [1000, 'l'],
            'l', 'ltr' => [1, 'l'],
            'pc', 'pcs', 'piece' => [1, 'pc'],
            default => [1, $unit],
        };

        $this->unit = $base;

        return round((float) $this->price / ($value / $factor), 4);
    }

    public function routeSegment(): string
    {
        return $this->slug.'-'.$this->id;
    }

    public function flyerUrl(): string
    {
        $url = $this->flyer->url($this->page->number);

        return $url.(str_contains($url, '?') ? '&' : '?').'offer='.$this->id;
    }

    public static function idFromSegment(string $segment): ?int
    {
        return preg_match('/-(\d+)$/', $segment, $m) === 1 ? (int) $m[1] : null;
    }

    /**
     * @return array<int, string|null>
     */
    public function searchableValues(): array
    {
        return [$this->title_ar, $this->title_en, $this->brand, $this->raw_title];
    }
}
