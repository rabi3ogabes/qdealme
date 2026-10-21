<?php

namespace App\Filament\Concerns;

use App\Enums\AdminGroup;
use UnitEnum;

/**
 * Translated navigation groups and labels for resources. Resources declare
 * a group key and a translation key for their labels.
 */
trait AdminNavigation
{
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return AdminGroup::from(static::$groupKey);
    }

    public static function getModelLabel(): string
    {
        return __('admin.models.'.static::$labelKey.'.one');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.models.'.static::$labelKey.'.many');
    }
}
