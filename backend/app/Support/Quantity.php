<?php

namespace App\Support;

use InvalidArgumentException;

final class Quantity
{
    public const MAX = '99999999999999.9999';

    public static function valid(mixed $value, bool $allowZero = false): bool
    {
        return is_string($value) && preg_match('/\\A[0-9]{1,14}(?:\\.[0-9]{1,4})?\\z/D', $value) === 1
            && bccomp($value, '0', 4) >= ($allowZero ? 0 : 1) && bccomp($value, self::MAX, 4) <= 0;
    }

    public static function normalize(string $value): string
    {
        if (! self::valid($value, true)) {
            throw new InvalidArgumentException('Invalid fixed-scale quantity.');
        }

        return bcadd($value, '0', 4);
    }
}
