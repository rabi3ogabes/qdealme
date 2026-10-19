<?php

namespace App\Enums;

enum Verification: string
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
}
