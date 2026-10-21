<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FlyerStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Processing = 'processing';
    case Failed = 'failed';
    case InReview = 'in_review';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Expired = 'expired';
    case Archived = 'archived';

    public function label(): string
    {
        return __('flyer.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Processing => 'info',
            self::Failed => 'danger',
            self::InReview => 'warning',
            self::Scheduled => 'primary',
            self::Published => 'success',
            self::Expired => 'gray',
            self::Archived => 'gray',
        };
    }

    public function isPublic(): bool
    {
        return in_array($this, [self::Published, self::Expired], true);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return $this->color();
    }
}
