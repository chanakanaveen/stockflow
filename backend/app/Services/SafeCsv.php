<?php

namespace App\Services;

class SafeCsv
{
    public static function text(string $value): string
    {
        // Treat leading controls/whitespace and locale-specific formula prefixes as unsafe.
        return preg_match('/^(?:[\\x00-\\x20\\x7f]|[=+@\\-＝＋－＠])/u', $value) ? "'".$value : $value;
    }
}
