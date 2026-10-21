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
                $words = preg_split('/\s+/u', $variant, -1, PREG_SPLIT_NO_EMPTY) ?: [];

                // Every word must match the start of a word in the index
                // (optionally after the Arabic article "ال"), so "rice"
                // finds "Basmati Rice" but not "Prices".
                $q->orWhere(function (Builder $all) use ($words): void {
                    foreach ($words as $word) {
                        $w = addcslashes($word, '%_\\');
                        $column = $this->getTable().'.search_index';

                        $all->where(fn (Builder $one) => $one
                            ->where($column, 'like', $w.'%')
                            ->orWhere($column, 'like', '% '.$w.'%')
                            ->orWhere($column, 'like', 'ال'.$w.'%')
                            ->orWhere($column, 'like', '% ال'.$w.'%'));
                    }
                });
            }
        });
    }
}
