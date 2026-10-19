<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Branch extends Model
{
    use HasLocalizedAttributes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'is_active' => 'boolean',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function getAddressAttribute(): ?string
    {
        return $this->localized('address');
    }

    public function mapUrl(): ?string
    {
        if ($this->lat === null || $this->lng === null) {
            return null;
        }

        return sprintf('https://www.google.com/maps/search/?api=1&query=%s,%s', $this->lat, $this->lng);
    }
}
