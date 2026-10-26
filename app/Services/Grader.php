<?php

namespace App\Services;

use App\Models\Item;
use InvalidArgumentException;

/**
 * Grading is server-side and separate from rendering (Pilot v3.2, 5.1).
 * Input and key are canonical labels, independent of display order.
 */
class Grader
{
    public function grade(Item $item, string $canonicalOption): bool
    {
        if (! in_array($canonicalOption, OptionOrder::CANONICAL, true)) {
            throw new InvalidArgumentException("Unknown option [{$canonicalOption}].");
        }

        return hash_equals($item->getAttribute('key'), $canonicalOption);
    }
}
