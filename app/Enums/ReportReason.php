<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReportReason: string implements HasLabel
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

    public function getLabel(): string
    {
        return $this->label();
    }
}
