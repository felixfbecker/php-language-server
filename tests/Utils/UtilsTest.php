<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Utils;

use PHPUnit\Framework\TestCase;
use function LanguageServer\{strStartsWith};

class UtilsTest extends TestCase
{
    public function testStrStartsWithDataProvider(): array {
        return [
            ['a', 'b', false],
            ['', 'a', false],
            ['foobar', 'bar', false],

            ['a', '', true],
            ['', '', true],

            ['foobar', 'foob', true],
            ['foobar', 'f', true],
            ['FOOBAR', 'foo', false],
            ['foobar', 'foobar', true]
        ];
    }

    /**
     * @dataProvider testStrStartsWithDataProvider
     */
    public function testStrStartsWith($haystack, $prefix, $expectedResult)
    {
        $this->assertEquals(strStartsWith($haystack, $prefix), $expectedResult);
    }
}
