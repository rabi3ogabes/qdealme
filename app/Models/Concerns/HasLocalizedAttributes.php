<?php

namespace App\Models\Concerns;

trait HasLocalizedAttributes
{
    /**
     * Return the value of a bilingual attribute pair for the current (or given)
     * locale, falling back to the other language when one side is empty.
     */
    public function localized(string $attribute, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $fallback = $locale === 'ar' ? 'en' : 'ar';

        $value = $this->getAttribute($attribute.'_'.$locale);

        if ($value === null || $value === '') {
            $value = $this->getAttribute($attribute.'_'.$fallback);
        }

        return $value;
    }

    public function getNameAttribute(): ?string
    {
        return $this->localized('name');
    }
}
