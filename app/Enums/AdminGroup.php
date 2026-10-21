<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Admin sidebar groups, in display order.
 */
enum AdminGroup: string implements HasLabel
{
    case Content = 'content';
    case Catalog = 'catalog';
    case Inbox = 'inbox';
    case Locations = 'locations';
    case People = 'people';

    public function getLabel(): string
    {
        return __('admin.groups.'.$this->value);
    }
}
