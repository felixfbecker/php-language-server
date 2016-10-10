<?php
declare(strict_types = 1);

namespace LanguageServer\Tests;

use PHPUnit\Framework\TestCase;
use LanguageServer\Formatter;

class FormatterTest extends TestCase
{

    public function testFormat()
    {
        $formatter = new Formatter();

        $input = file_get_contents(__DIR__ . '/../fixtures/format.php');
        $output = file_get_contents(__DIR__ . '/../fixtures/format_expected.php');

        $edits = $formatter->format($input, 'file:///whatever');
        $this->assertSame($output, $edits[0]->newText);
    }

    public function testFormatNoChange()
    {
        $formatter = new Formatter();
        $expected = file_get_contents(__DIR__ . '/../fixtures/format_expected.php');

        $edits = $formatter->format($expected, 'file:///whatever');
        $this->assertSame([], $edits);
    }
}
