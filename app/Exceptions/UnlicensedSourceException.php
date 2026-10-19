<?php

namespace App\Exceptions;

use RuntimeException;

class UnlicensedSourceException extends RuntimeException
{
    public static function forSource(?int $sourceId): self
    {
        return new self(sprintf(
            'Flyer source #%s is not approved. Flyers may only be created from sources with an approved licence.',
            $sourceId ?? 'null'
        ));
    }
}
