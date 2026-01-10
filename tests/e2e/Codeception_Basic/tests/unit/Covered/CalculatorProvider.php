<?php

namespace Codeception_Basic\Tests\unit\Covered;

final class CalculatorProvider
{
    public static function provideAdditions(): iterable
    {
        yield [2, 3, 5];
        yield [-5, 5, 0];
        yield 'with a key' => [-3, -7, -10];
        yield 'with a key with (\'"#::&) special characters' => [-5, -5, -10];
    }
}