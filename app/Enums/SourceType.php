<?php

namespace App\Enums;

enum SourceType: string
{
    case RetailerUpload = 'retailer_upload';
    case AdminUpload = 'admin_upload';
    case RetailerFeed = 'retailer_feed';

    public function label(): string
    {
        return __('source.type.'.$this->value);
    }
}
