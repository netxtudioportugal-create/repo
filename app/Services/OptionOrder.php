<?php

namespace App\Services;

/**
 * Deterministic shuffle of the four canonical options. The seed is the
 * session id plus the item code, so each session gets its own order and a
 * reload never reshuffles what the learner already saw.
 */
class OptionOrder
{
    public const CANONICAL = ['A', 'B', 'C', 'D'];

    public static function for(string $seed): string
    {
        $options = self::CANONICAL;
        $bytes = hash('sha256', $seed, true);

        for ($i = count($options) - 1; $i > 0; $i--) {
            $j = ord($bytes[$i]) % ($i + 1);
            [$options[$i], $options[$j]] = [$options[$j], $options[$i]];
        }

        return implode('', $options);
    }
}
