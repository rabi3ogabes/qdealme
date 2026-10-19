<?php

namespace App\Models\Concerns;

use App\Support\Arabic;
use Illuminate\Database\Eloquent\Builder;

/**
 * Maintains a normalised, bilingual "search_index" column so that LIKE
 * queries match regardless of hamza/ta-marbuta spelling or diacritics.
 */
trait Searchable
{
    public static function bootSearchable(): void
    {
        static::saving(function ($model): void {
            $model->search_index = $model->buildSearchIndex();
        });
    }

    /**
     * @return array<int, string|null>
     */
    abstract public function searchableValues(): array;

    public function buildSearchIndex(): string
    {
        $parts = array_filter($this->searchableValues(), fn ($v) => filled($v));

        return Arabic::normalize(implode(' ', $parts));
    }

    /**
     * Match any of the expanded variants of the query.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $variants = Arabic::expand(Arabic::normalize($term));

        return $query->where(function (Builder $q) use ($variants): void {
            foreach ($variants as $variant) {
                foreach (preg_split('/\s+/', $variant, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                    $q->orWhere($this->getTable().'.search_index', 'like', '%'.addcslashes($word, '%_\\').'%');
                }
            }
        });
    }
}
