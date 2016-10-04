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
        
        $edits = $formatter->format($input, 'whatever');
        $this->assertTrue($edits[0]->newText === $output);
    }

    public function testFormatNoChange()
    {
        $formatter = new Formatter();
        $expected = file_get_contents(__DIR__ . '/../fixtures/format_expected.php');
        
        $edits = $formatter->format($expected, 'whatever');
        $this->assertTrue($edits == []);
    }

}