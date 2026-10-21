<?php

namespace App\Models;

use App\Enums\LicenseStatus;
use App\Enums\SourceType;
use App\Filament\Store\Pages\UploadFlyer;
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
            'last_pulled_at' => 'datetime',
            'last_uploaded_at' => 'datetime',
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

    /**
     * The private link where the store's own team uploads its flyer. A token
     * is created the first time the link is needed.
     */
    public function uploadUrl(): string
    {
        if (blank($this->upload_token)) {
            // Only fills an empty token, so copies of this record loaded
            // elsewhere in the same request all end up with the same link.
            static::query()->whereKey($this->getKey())->whereNull('upload_token')
                ->update(['upload_token' => bin2hex(random_bytes(24))]);

            $this->upload_token = static::query()->whereKey($this->getKey())->value('upload_token');
            $this->syncOriginalAttribute('upload_token');
        }

        return UploadFlyer::url($this->upload_token);
    }

    /**
     * Issues a new upload link; the previous one stops working at once.
     */
    public function rotateUploadToken(): void
    {
        $this->forceFill(['upload_token' => bin2hex(random_bytes(24))])->save();
    }

    public static function findByUploadToken(string $token): ?self
    {
        if (strlen($token) !== 48 || ! ctype_xdigit($token)) {
            return null;
        }

        return static::query()->approved()->where('upload_token', $token)->with('retailer')->first();
    }

    public function label(): string
    {
        return sprintf('%s · %s (%s)', $this->retailer?->name, $this->type->label(), $this->license_status->label());
    }
}
