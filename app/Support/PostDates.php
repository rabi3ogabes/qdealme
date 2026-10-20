<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Reads the offer dates from the text of a store's post, in Arabic or
 * English, for example "20 - 26 October", "من ٢٠ إلى ٢٦ أكتوبر",
 * "20/10 - 26/10/2026" or "valid until 26 Oct".
 *
 * Day-first numeric dates are assumed, as used in Qatar. Returns null when
 * the text holds no plausible date range, so staff simply set the dates.
 */
final class PostDates
{
    private const MONTHS = [
        'january' => 1, 'jan' => 1, 'february' => 2, 'feb' => 2, 'march' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4, 'may' => 5, 'june' => 6, 'jun' => 6, 'july' => 7, 'jul' => 7,
        'august' => 8, 'aug' => 8, 'september' => 9, 'sept' => 9, 'sep' => 9,
        'october' => 10, 'oct' => 10, 'november' => 11, 'nov' => 11, 'december' => 12, 'dec' => 12,
        'يناير' => 1, 'كانون الثاني' => 1, 'فبراير' => 2, 'شباط' => 2, 'مارس' => 3, 'آذار' => 3, 'اذار' => 3,
        'أبريل' => 4, 'ابريل' => 4, 'إبريل' => 4, 'نيسان' => 4, 'مايو' => 5, 'أيار' => 5, 'ايار' => 5,
        'يونيو' => 6, 'يونيه' => 6, 'حزيران' => 6, 'يوليو' => 7, 'يوليه' => 7, 'تموز' => 7,
        'أغسطس' => 8, 'اغسطس' => 8, 'آب' => 8, 'سبتمبر' => 9, 'أيلول' => 9, 'ايلول' => 9,
        'أكتوبر' => 10, 'اكتوبر' => 10, 'تشرين الأول' => 10, 'تشرين الاول' => 10,
        'نوفمبر' => 11, 'تشرين الثاني' => 11, 'ديسمبر' => 12, 'كانون الأول' => 12, 'كانون الاول' => 12,
    ];

    private const RANGE = '\s*(?:-|–|—|~|to|till|until|إلى|الى|حتى|لغاية)\s*';

    private const LONGEST_SPAN_DAYS = 62;

    /**
     * @return array{from: CarbonImmutable, until: CarbonImmutable}|null
     */
    public static function find(?string $text, ?CarbonImmutable $today = null): ?array
    {
        $text = self::normalise((string) $text);

        if ($text === '') {
            return null;
        }

        $today ??= CarbonImmutable::today(config('qdealme.timezone'));

        return self::range($text, $today) ?? self::fromSeparateDates($text, $today);
    }

    private static function normalise(string $text): string
    {
        $text = strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '،' => ',', "\u{200F}" => '', "\u{200E}" => '', 'ـ' => '',
        ]);

        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($text)));
    }

    private static function monthPattern(): string
    {
        $names = array_keys(self::MONTHS);
        usort($names, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        return implode('|', array_map(fn (string $name) => preg_quote($name, '/'), $names));
    }

    /**
     * One day range sharing a month: "20 - 26 October", "October 20 - 26", "20 - 26/10".
     *
     * @return array{from: CarbonImmutable, until: CarbonImmutable}|null
     */
    private static function range(string $text, CarbonImmutable $today): ?array
    {
        $month = self::monthPattern();
        $range = self::RANGE;
        $year = '(?:\s*,?\s*(\d{4}))?';
        $patterns = [
            "/(?<![\d\/.])(\d{1,2}){$range}(\d{1,2})\s*(?:of\s+)?({$month})(?![\p{L}]){$year}/u" => [1, 2, 3, 4],
            "/(?<![\p{L}])({$month})\s+(\d{1,2}){$range}(\d{1,2})(?!\d){$year}/u" => [2, 3, 1, 4],
            "/(?<![\d\/.])(\d{1,2}){$range}(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?(?![\d\/])/u" => [1, 2, 3, 4],
        ];

        foreach ($patterns as $pattern => [$fromDay, $untilDay, $monthGroup, $yearGroup]) {
            if (preg_match($pattern, $text, $m) !== 1) {
                continue;
            }

            $monthNumber = self::month($m[$monthGroup]);
            $explicitYear = ($m[$yearGroup] ?? '') !== '' ? (int) $m[$yearGroup] : null;
            $from = self::date((int) $m[$fromDay], $monthNumber, $explicitYear, $today);
            $until = self::date((int) $m[$untilDay], $monthNumber, $explicitYear, $today);

            if ($found = self::plausible($from, $until, $explicitYear === null)) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Two separate dates ("20/10 - 26/10/2026", "from 20 Oct until 26 Oct"),
     * or a single end date ("valid until 26 October") starting today.
     *
     * @return array{from: CarbonImmutable, until: CarbonImmutable}|null
     */
    private static function fromSeparateDates(string $text, CarbonImmutable $today): ?array
    {
        $month = self::monthPattern();
        $ordinal = '(?:st|nd|rd|th)?';
        $patterns = [
            // 20/10 or 20/10/2026; 20-10-2026 and 20.10.2026 only with a year, so prices and sizes are not dates.
            '/(?<![\d\/.\-])(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?(?![\d\/])/u' => ['d' => 1, 'm' => 2, 'y' => 3],
            '/(?<![\d\/.\-])(\d{1,2})[.\-](\d{1,2})[.\-](\d{4})(?![\d.\-])/u' => ['d' => 1, 'm' => 2, 'y' => 3],
            "/(?<![\d\/.])(\d{1,2}){$ordinal}\s*(?:of\s+)?({$month})(?![\p{L}])(?:\s*,?\s*(\d{4}))?/u" => ['d' => 1, 'm' => 2, 'y' => 3],
            "/(?<![\p{L}])({$month})\s+(\d{1,2}){$ordinal}(?!\d)(?:\s*,?\s*(\d{4}))?/u" => ['d' => 2, 'm' => 1, 'y' => 3],
        ];

        $dates = [];

        foreach ($patterns as $pattern => $groups) {
            preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as $m) {
                $year = isset($m[$groups['y']]) && $m[$groups['y']][0] !== '' ? (int) $m[$groups['y']][0] : null;
                $date = self::date((int) $m[$groups['d']][0], self::month($m[$groups['m']][0]), $year, $today);

                if ($date !== null) {
                    $dates[$m[0][1]] ??= ['date' => $date, 'year_given' => $year !== null];
                }
            }
        }

        ksort($dates);
        $dates = array_values($dates);

        if (count($dates) >= 2) {
            [$first, $second] = $dates;

            return self::plausible($first['date'], $second['date'], ! $second['year_given']);
        }

        if (count($dates) === 1 && $dates[0]['date']->gte($today)) {
            return self::plausible($today, $dates[0]['date'], false);
        }

        return null;
    }

    private static function month(string $value): int
    {
        return ctype_digit($value) ? (int) $value : (self::MONTHS[$value] ?? 0);
    }

    private static function date(int $day, int $month, ?int $year, CarbonImmutable $today): ?CarbonImmutable
    {
        if ($year !== null && $year < 100) {
            $year += 2000;
        }

        $guessYear = $year === null;
        $year ??= $today->year;

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        $date = CarbonImmutable::create($year, $month, $day, 0, 0, 0, $today->getTimezone());

        // A post from late December about "2 January" means next year.
        if ($guessYear && $date->lt($today->subMonths(6))) {
            $date = $date->addYear();
        }

        return $date;
    }

    /**
     * @return array{from: CarbonImmutable, until: CarbonImmutable}|null
     */
    private static function plausible(?CarbonImmutable $from, ?CarbonImmutable $until, bool $untilYearGuessed): ?array
    {
        if ($from === null || $until === null) {
            return null;
        }

        // "28 December - 3 January": the end date rolls into the next year.
        if ($until->lt($from) && $untilYearGuessed && $until->addYear()->diffInDays($from, true) <= self::LONGEST_SPAN_DAYS) {
            $until = $until->addYear();
        }

        if ($until->lt($from) || $from->diffInDays($until, true) > self::LONGEST_SPAN_DAYS) {
            return null;
        }

        return ['from' => $from, 'until' => $until];
    }
}
