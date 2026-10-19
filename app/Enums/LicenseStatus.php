<?php

namespace App\Enums;

enum LicenseStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Revoked = 'revoked';

    public function label(): string
    {
        return __('source.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Revoked => 'danger',
        };
    }
}
