<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class FlyerPage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'derivatives' => 'array',
        ];
    }

    public function flyer(): BelongsTo
    {
        return $this->belongsTo(Flyer::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function originalUrl(): string
    {
        return Storage::disk('public')->url($this->original_path);
    }

    /**
     * Best URL for a given display width. Falls back to the original when no
     * derivative is wide enough (or none exist, e.g. vector demo pages).
     */
    public function url(int $width = 640, string $format = 'webp'): string
    {
        $candidates = collect($this->derivatives[$format] ?? [])
            ->sortKeys()
            ->filter(fn ($path, $w) => (int) $w >= $width);

        $path = $candidates->first() ?? collect($this->derivatives[$format] ?? [])->sortKeys()->last();

        return $path ? Storage::disk('public')->url($path) : $this->originalUrl();
    }

    public function srcset(string $format = 'webp'): ?string
    {
        $set = $this->derivatives[$format] ?? [];

        if ($set === []) {
            return null;
        }

        return collect($set)
            ->sortKeys()
            ->map(fn ($path, $w) => Storage::disk('public')->url($path).' '.$w.'w')
            ->implode(', ');
    }

    public function aspectRatio(): string
    {
        if ($this->width && $this->height) {
            return $this->width.' / '.$this->height;
        }

        return '3 / 4';
    }
}
