<?php
declare(strict_types=1);

namespace LanguageServer\Tests;

use LanguageServer\Options;
use PHPUnit\Framework\TestCase;

class OptionsTest extends TestCase
{
    public function testFileTypesOption()
    {
        $expected = [
            '.php',
            '.valid'
        ];

        $options = new Options;
        $options->setFileTypes([
            '.php',
            false,
            12345,
            '.valid'
        ]);

        $this->assertSame($expected, $options->fileTypes);
    }

    public function testConvertFileSize()
    {
        $options = new Options();

        $options->setFileSizeLimit('150K');
        $this->assertEquals(150000, $options->fileSizeLimit);

        $options->setFileSizeLimit('15M');
        $this->assertEquals(15000000, $options->fileSizeLimit);

        $options->setFileSizeLimit('15G');
        $this->assertEquals(15000000000, $options->fileSizeLimit);

        $options->setFileSizeLimit('-1');
        $this->assertEquals(PHP_INT_MAX, $options->fileSizeLimit);
    }
}
