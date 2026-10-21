<?php

namespace App\Support;

/**
 * Light-weight Arabic text normalisation used for search so that "إيفون",
 * "ايفون" and "أيفون" match each other, and diacritics never get in the way.
 */
final class Arabic
{
    private const DIACRITICS = '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u';

    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = (string) preg_replace(self::DIACRITICS, '', $text);

        return strtr($text, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه',
            'ى' => 'ي',
            'ؤ' => 'و',
            'ئ' => 'ي',
            'گ' => 'ك', 'ک' => 'ك',
            'ی' => 'ي',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    /**
     * Convert Western digits to Eastern Arabic-Indic digits.
     */
    public static function toArabicDigits(string $text): string
    {
        return strtr($text, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
    }

    /**
     * Search synonyms (Arabic <-> English product names) applied to queries.
     *
     * @return array<int, string>
     */
    public static function expand(string $normalizedQuery): array
    {
        $synonyms = config('synonyms', []);
        $terms = [$normalizedQuery];

        foreach ($synonyms as $group) {
            $group = array_map(fn ($t) => self::normalize($t), $group);
            foreach ($group as $term) {
                if (str_contains($normalizedQuery, $term)) {
                    foreach ($group as $alt) {
                        if ($alt !== $term) {
                            $terms[] = str_replace($term, $alt, $normalizedQuery);
                        }
                    }
                }
            }
        }

        return array_values(array_unique($terms));
    }
}
