<?php

namespace App\Models;

use App\Enums\RetailerType;
use App\Models\Concerns\HasLocalizedAttributes;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Retailer extends Model
{
    use HasFactory;
    use HasLocalizedAttributes;
    use Searchable;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => RetailerType::class,
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function flyers(): HasMany
    {
        return $this->hasMany(Flyer::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(FlyerSource::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows')->withPivot('created_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getAboutAttribute(): ?string
    {
        return $this->localized('about');
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return str_starts_with($this->logo_path, 'http')
            ? $this->logo_path
            : Storage::disk('public')->url($this->logo_path);
    }

    public function whatsappUrl(): ?string
    {
        if (! $this->whatsapp_number) {
            return null;
        }

        return 'https://wa.me/'.preg_replace('/\D+/', '', $this->whatsapp_number);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return array<int, string|null>
     */
    public function searchableValues(): array
    {
        return [$this->name_ar, $this->name_en, $this->type?->label()];
    }
}
