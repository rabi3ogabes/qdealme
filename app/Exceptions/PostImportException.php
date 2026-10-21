<?php

namespace App\Exceptions;

use App\Models\Flyer;
use RuntimeException;

/**
 * A flyer could not be imported from a store's post. Messages are
 * translated because they are shown to staff in the admin panel.
 */
class PostImportException extends RuntimeException
{
    public ?Flyer $existing = null;

    public static function because(string $reason, array $replace = []): self
    {
        return new self(__('admin.import.errors.'.$reason, $replace));
    }

    public static function duplicate(Flyer $existing): self
    {
        $e = self::because('duplicate', ['title' => $existing->title, 'id' => $existing->id]);
        $e->existing = $existing;

        return $e;
    }
}
