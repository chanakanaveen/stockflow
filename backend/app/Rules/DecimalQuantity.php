<?php

namespace App\Rules;

use App\Support\Quantity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DecimalQuantity implements ValidationRule
{
    public function __construct(private readonly bool $allowZero = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Quantity::valid($value, $this->allowZero)) {
            $fail('The :attribute must be a '.($this->allowZero ? 'nonnegative' : 'positive').' decimal string with at most 14 integer and 4 fractional digits.');
        }
    }
}
