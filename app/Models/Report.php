<?php

namespace App\Models;

use App\Enums\ReportReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
        ];
    }

    public function flyer(): BelongsTo
    {
        return $this->belongsTo(Flyer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
