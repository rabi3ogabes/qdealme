<?php

namespace App\Enums;

enum ReportReason: string
{
    case WrongPrice = 'wrong_price';
    case Expired = 'expired';
    case Inappropriate = 'inappropriate';
    case BrokenPages = 'broken_pages';
    case Other = 'other';

    public function label(): string
    {
        return __('report.reason.'.$this->value);
    }
}
