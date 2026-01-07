<?php

namespace Codeception_Basic\Tests\unit\Covered;

use function Codeception_Basic\Covered\formatName;

class FormatNameFunctionTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
        require_once __DIR__ . '/../../../src/Covered/functions.php';
    }

    protected function _after()
    {
    }

    public function testFormatFullName()
    {
        $this->assertSame('John Doe', formatName('John', 'Doe'));
    }

    public function testFormatFirstNameOnly()
    {
        $this->assertSame('John', formatName('John', ''));
    }

    public function testFormatLastNameOnly()
    {
        $this->assertSame('Doe', formatName('', 'Doe'));
    }

    public function testFormatEmptyNames()
    {
        $this->assertSame('Anonymous', formatName('', ''));
    }

    public function testFormatWithSpaces()
    {
        $this->assertSame('Mary Jane', formatName('Mary', 'Jane'));
    }
}
