<?php

namespace Codeception_With_Suite_Overridings\Tests\unit\Covered;

use Codeception_With_Suite_Overridings\Covered\Calculator;

class CalculatorTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    private Calculator $calculator;

    protected function _before()
    {
        $this->calculator = new Calculator();
    }

    protected function _after()
    {
    }

    /**
     * @dataProvider \Codeception_With_Suite_Overridings\Tests\unit\Covered\CalculatorProvider::provideAdditions
     */
    public function testAddition(int $a, int $b, int $expected): void
    {
        $this->assertSame($expected, $this->calculator->add($a, $b));
    }

    /**
     * @dataProvider provideSubtractions
     */
    public function testSubtraction(int $a, int $b, int $expected): void
    {
        $this->assertSame($expected, $this->calculator->subtract($a, $b));
    }

    public static function provideSubtractions(): iterable
    {
        yield [3, 2, 1];
        yield [-5, 5, -10];
        yield 'with a key' => [-3, -7, 4];
        yield 'with a key with (\'"#::&|) special characters' => [5, 5, 0];
    }

    public function testMultiplication(): void
    {
        $this->assertSame(6, $this->calculator->multiply(2, 3));
        $this->assertSame(-25, $this->calculator->multiply(-5, 5));
        $this->assertSame(21, $this->calculator->multiply(-3, -7));
        $this->assertSame(0, $this->calculator->multiply(5, 0));
    }

    public function testDivision(): void
    {
        $this->assertSame(2.0, $this->calculator->divide(6, 3));
        $this->assertSame(-1.0, $this->calculator->divide(-5, 5));
        $this->assertSame(2.5, $this->calculator->divide(5, 2));
    }

    public function testDivisionByZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Division by zero');
        $this->calculator->divide(5, 0);
    }

    public function testIsPositive(): void
    {
        $this->assertTrue($this->calculator->isPositive(5));
        $this->assertTrue($this->calculator->isPositive(0));
        $this->assertFalse($this->calculator->isPositive(-5));
    }

    public function testAbsolute(): void
    {
        $this->assertSame(5, $this->calculator->absolute(5));
        $this->assertSame(5, $this->calculator->absolute(-5));
        $this->assertSame(0, $this->calculator->absolute(0));
    }
}
