<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A retailer feed could not be pulled. Messages are translated because they
 * are shown to staff in the admin panel.
 */
class FeedPullException extends RuntimeException
{
    public static function because(string $reason, array $replace = []): self
    {
        return new self(__('feed.errors.'.$reason, $replace));
    }
}
