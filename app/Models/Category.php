<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use App\Support\Arabic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasLocalizedAttributes;

    protected $guarded = [];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort')->orderBy('name_en');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id')->orderBy('sort');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $variants = Arabic::expand(Arabic::normalize($term));

        return $query->where(function (Builder $q) use ($variants): void {
            foreach ($variants as $variant) {
                $like = '%'.addcslashes($variant, '%_\\').'%';
                $q->orWhereRaw('LOWER(name_en) LIKE ?', [$like])
                    ->orWhere('name_ar', 'like', $like);
            }
        });
    }

    /**
     * IDs of this category and all of its descendants (two levels are seeded,
     * but the walk is recursive so deeper trees keep working).
     *
     * @return array<int, int>
     */
    public function subtreeIds(): array
    {
        $ids = [$this->id];

        foreach ($this->children as $child) {
            $ids = [...$ids, ...$child->subtreeIds()];
        }

        return $ids;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
