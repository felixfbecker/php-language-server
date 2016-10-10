<?php
declare(strict_types = 1);

namespace LanguageServer\Tests;

use PHPUnit\Framework\TestCase;
use LanguageServer\Formatter;

class FormatterTest extends TestCase
{

    public function testFormat()
    {
        $input = file_get_contents(__DIR__ . '/../fixtures/format.php');
        $output = file_get_contents(__DIR__ . '/../fixtures/format_expected.php');

        $edits = Formatter::format($input, 'file:///whatever');
        $this->assertSame($output, $edits[0]->newText);
    }

    public function testFormatNoChange()
    {
        $expected = file_get_contents(__DIR__ . '/../fixtures/format_expected.php');

        $edits = Formatter::format($expected, 'file:///whatever');
        $this->assertSame([], $edits);
    }
}
