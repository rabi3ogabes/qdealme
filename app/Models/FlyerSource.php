<?php

namespace App\Models;

use App\Enums\LicenseStatus;
use App\Enums\SourceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlyerSource extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => SourceType::class,
            'license_status' => LicenseStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function flyers(): HasMany
    {
        return $this->hasMany(Flyer::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('license_status', LicenseStatus::Approved->value);
    }

    public function isApproved(): bool
    {
        return $this->license_status === LicenseStatus::Approved;
    }

    public function label(): string
    {
        return sprintf('%s · %s (%s)', $this->retailer?->name, $this->type->label(), $this->license_status->label());
    }
}
