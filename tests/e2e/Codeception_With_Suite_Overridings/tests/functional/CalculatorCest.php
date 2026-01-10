<?php

namespace Codeception_With_Suite_Overridings\Tests\functional;

use Codeception\Attribute\Examples;
use Codeception\Example;
use Codeception_With_Suite_Overridings\Covered\Calculator;
use Codeception_With_Suite_Overridings\FunctionalTester;

class CalculatorCest
{
    private Calculator $calculator;

    public function _before(FunctionalTester $I): void
    {
        $this->calculator = new Calculator();
    }

    /**
     * @dataProvider additionProvider
     */
    public function testAddition(FunctionalTester $I, Example $example): void
    {
        $I->wantTo('verify addition works correctly');

        $I->assertEquals($example['expected'], $this->calculator->add($example['a'], $example['b']));
    }

    protected function additionProvider(): array
    {
        return [
            ['a' => 2, 'b' => 3, 'expected' => 5],
            ['a' => -5, 'b' => 5, 'expected' => 0],
            'with a key' => ['a' => -3, 'b' => -7, 'expected' => -10],
            'with a key with (\'"#::&|) special characters' => ['a' => -5, 'b' => -5, 'expected' => -10],
        ];
    }

    #[Examples(3, 2, 1)]
    #[Examples(-5, 5, -10)]
    #[Examples('with a key', -3, -7, 4)]
    #[Examples('with a key with (\'"#::&|) special characters', 5, 5, 0)]
    public function testSubtraction(FunctionalTester $I, Example $example): void
    {
        $I->wantTo('verify subtraction works correctly');

        if (count($example) === 3) {
            $I->assertEquals($example[2], $this->calculator->subtract($example[0], $example[1]));
        } else {
            // Named example: first element is the label
            $I->assertEquals($example[3], $this->calculator->subtract($example[1], $example[2]));
        }
    }

    public function testMultiplication(FunctionalTester $I): void
    {
        $I->wantTo('verify multiplication works correctly');

        $I->assertEquals(6, $this->calculator->multiply(2, 3));
        $I->assertEquals(-25, $this->calculator->multiply(-5, 5));
        $I->assertEquals(21, $this->calculator->multiply(-3, -7));
        $I->assertEquals(0, $this->calculator->multiply(5, 0));
    }

    public function testDivision(FunctionalTester $I): void
    {
        $I->wantTo('verify division works correctly');

        $I->assertEquals(2.0, $this->calculator->divide(6, 3));
        $I->assertEquals(-1.0, $this->calculator->divide(-5, 5));
        $I->assertEquals(2.5, $this->calculator->divide(5, 2));
    }

    public function testDivisionByZeroThrowsException(FunctionalTester $I): void
    {
        $I->wantTo('verify division by zero throws an exception');

        $I->expectThrowable(
            new \InvalidArgumentException('Division by zero'),
            fn() => $this->calculator->divide(5, 0)
        );
    }

    public function testIsPositive(FunctionalTester $I): void
    {
        $I->wantTo('verify isPositive identifies positive numbers correctly');

        $I->assertTrue($this->calculator->isPositive(5));
        $I->assertTrue($this->calculator->isPositive(0));
        $I->assertFalse($this->calculator->isPositive(-5));
    }

    public function testAbsolute(FunctionalTester $I): void
    {
        $I->wantTo('verify absolute value works correctly');

        $I->assertEquals(5, $this->calculator->absolute(5));
        $I->assertEquals(5, $this->calculator->absolute(-5));
        $I->assertEquals(0, $this->calculator->absolute(0));
    }
}
