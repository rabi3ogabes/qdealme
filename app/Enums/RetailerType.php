<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RetailerType: string implements HasLabel
{
    case Supermarket = 'supermarket';
    case Hypermarket = 'hypermarket';
    case Electronics = 'electronics';
    case Pharmacy = 'pharmacy';
    case Fashion = 'fashion';
    case Furniture = 'furniture';
    case Other = 'other';

    public function label(): string
    {
        return __('retailer.type.'.$this->value);
    }

    /**
     * Quick-filter groups shown on the offers page. Hypermarkets are grouped
     * with supermarkets because shoppers think of them as one category.
     *
     * @return array<string, array<int, self>>
     */
    public static function filterGroups(): array
    {
        return [
            'supermarket' => [self::Supermarket, self::Hypermarket],
            'electronics' => [self::Electronics],
            'pharmacy' => [self::Pharmacy],
            'fashion' => [self::Fashion],
            'home' => [self::Furniture],
            'other' => [self::Other],
        ];
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
