<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case ContentAdmin = 'content_admin';
    case Reviewer = 'reviewer';
    case Shopper = 'shopper';

    public function label(): string
    {
        return __('user.role.'.$this->value);
    }

    public function isStaff(): bool
    {
        return $this !== self::Shopper;
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
