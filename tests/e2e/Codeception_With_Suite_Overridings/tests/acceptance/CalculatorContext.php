<?php

namespace Codeception_With_Suite_Overridings\Tests\acceptance;

use Codeception\Attribute\Given;
use Codeception\Attribute\Then;
use Codeception\Attribute\When;
use Codeception_With_Suite_Overridings\Covered\Calculator;
use PHPUnit\Framework\Assert;

class CalculatorContext
{
    private Calculator $calculator;
    private mixed $result;
    private ?\Throwable $exception = null;

    #[Given('I have a calculator')]
    public function iHaveACalculator(): void
    {
        $this->calculator = new Calculator();
        $this->result = null;
        $this->exception = null;
    }

    #[When('I add :a and :b')]
    public function iAdd(string $a, string $b): void
    {
        $this->result = $this->calculator->add((int) $a, (int) $b);
    }

    #[When('I subtract :b from :a')]
    public function iSubtract(string $b, string $a): void
    {
        $this->result = $this->calculator->subtract((int) $a, (int) $b);
    }

    #[When('I multiply :a by :b')]
    public function iMultiply(string $a, string $b): void
    {
        $this->result = $this->calculator->multiply((int) $a, (int) $b);
    }

    #[When('I divide :a by :b')]
    public function iDivide(string $a, string $b): void
    {
        $this->result = $this->calculator->divide((int) $a, (int) $b);
    }

    #[When('I try to divide :a by :b')]
    public function iTryToDivide(string $a, string $b): void
    {
        try {
            $this->calculator->divide((int) $a, (int) $b);
        } catch (\Throwable $e) {
            $this->exception = $e;
        }
    }

    #[When('/^I check if (-?\d+) is positive$/')]
    public function iCheckIfIsPositive(string $number): void
    {
        $this->result = $this->calculator->isPositive((int) $number);
    }

    #[Then('the result should be :expected')]
    public function theResultShouldBe(string $expected): void
    {
        Assert::assertEquals((int) $expected, $this->result);
    }

    #[Then('the result should be true')]
    public function theResultShouldBeTrue(): void
    {
        Assert::assertTrue($this->result);
    }

    #[Then('the result should be false')]
    public function theResultShouldBeFalse(): void
    {
        Assert::assertFalse($this->result);
    }

    #[Then('I should get an error :message')]
    public function iShouldGetAnError(string $message): void
    {
        Assert::assertNotNull($this->exception, 'Expected an exception to be thrown');
        Assert::assertInstanceOf(\InvalidArgumentException::class, $this->exception);
        Assert::assertSame($message, $this->exception->getMessage());
    }
}
