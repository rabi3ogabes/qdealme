<?php

namespace App\Enums;

enum UserRole: string
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
}
