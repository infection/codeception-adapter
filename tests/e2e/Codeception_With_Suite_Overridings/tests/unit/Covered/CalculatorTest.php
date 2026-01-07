<?php

namespace Codeception_With_Suite_Overridings\Tests\unit\Covered;

use Codeception_With_Suite_Overridings\Covered\Calculator;

class CalculatorTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    public function testAddition()
    {
        $calculator = new Calculator();
        $this->assertSame(5, $calculator->add(2, 3));
        $this->assertSame(0, $calculator->add(-5, 5));
        $this->assertSame(-10, $calculator->add(-3, -7));
    }

    public function testSubtraction()
    {
        $calculator = new Calculator();
        $this->assertSame(1, $calculator->subtract(3, 2));
        $this->assertSame(-10, $calculator->subtract(-5, 5));
        $this->assertSame(4, $calculator->subtract(-3, -7));
    }

    public function testMultiplication()
    {
        $calculator = new Calculator();
        $this->assertSame(6, $calculator->multiply(2, 3));
        $this->assertSame(-25, $calculator->multiply(-5, 5));
        $this->assertSame(21, $calculator->multiply(-3, -7));
        $this->assertSame(0, $calculator->multiply(5, 0));
    }

    public function testDivision()
    {
        $calculator = new Calculator();
        $this->assertSame(2.0, $calculator->divide(6, 3));
        $this->assertSame(-1.0, $calculator->divide(-5, 5));
        $this->assertSame(2.5, $calculator->divide(5, 2));
    }

    public function testDivisionByZero()
    {
        $calculator = new Calculator();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Division by zero');
        $calculator->divide(5, 0);
    }

    public function testIsPositive()
    {
        $calculator = new Calculator();
        $this->assertTrue($calculator->isPositive(5));
        $this->assertTrue($calculator->isPositive(0));
        $this->assertFalse($calculator->isPositive(-5));
    }

    public function testAbsolute()
    {
        $calculator = new Calculator();
        $this->assertSame(5, $calculator->absolute(5));
        $this->assertSame(5, $calculator->absolute(-5));
        $this->assertSame(0, $calculator->absolute(0));
    }
}
