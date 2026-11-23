<?php

namespace App\Support;

/**
 * Picks the noun form for a count. Laravel's trans_choice() does not resolve
 * the plural rule for hyphenated locales such as "ru-UA", so the choice is
 * made here from the number of forms stored in the lang key:
 *
 * - "one|other" (en, es, pt): singular only for 1.
 * - "one|few|many" (uk, ru): East Slavic rule (1, 21 / 2-4, 22-24 / rest).
 */
class Plural
{
    /** Returns '' when the current locale has no such key. */
    public static function word(int $n, string $key): string
    {
        $forms = __($key);
        if ($forms === $key) {
            return '';
        }

        $forms = explode('|', $forms);

        if (count($forms) === 2) {
            return $n === 1 ? $forms[0] : $forms[1];
        }
        if (count($forms) !== 3) {
            return $forms[0];
        }

        $mod10 = $n % 10;
        $mod100 = $n % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return $forms[0];
        }
        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return $forms[1];
        }

        return $forms[2];
    }
}
