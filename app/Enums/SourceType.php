<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SourceType: string implements HasLabel
{
    case RetailerUpload = 'retailer_upload';
    case AdminUpload = 'admin_upload';
    case RetailerFeed = 'retailer_feed';
    case SocialMedia = 'social_media';

    public function label(): string
    {
        return __('source.type.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
