<?php

namespace Tests\Unit;

use App\Support\Quantity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QuantityTest extends TestCase
{
    /** @return array<string,array{mixed}> */
    public static function invalid(): array
    {
        return ['number' => [1.25], 'zero' => ['0'], 'negative' => ['-1'], 'exponent' => ['1e2'], 'precision' => ['1.00001'], 'overflow' => ['100000000000000'], 'whitespace' => [' 1'], 'missing_integer' => ['.5'], 'null' => [null]];
    }

    #[DataProvider('invalid')]
    public function test_rejects_non_positive_or_imprecise_quantities(mixed $value): void
    {
        $this->assertFalse(Quantity::valid($value));
    }

    public function test_exact_decimal_normalization_and_maximum(): void
    {
        $this->assertSame('0.1000', Quantity::normalize('0.1'));
        $this->assertSame('1.2300', Quantity::normalize('0001.23'));
        $this->assertTrue(Quantity::valid(Quantity::MAX));
        $this->assertTrue(Quantity::valid('0', true));
        $this->assertSame('0.3000', bcadd(Quantity::normalize('0.1'), Quantity::normalize('0.2'), 4));
    }
}
