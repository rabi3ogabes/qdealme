<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Verification: string implements HasColor, HasLabel
{
    case Unverified = 'unverified';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('offer.verification.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Unverified => 'warning',
            self::Verified => 'success',
            self::Rejected => 'danger',
        };
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
