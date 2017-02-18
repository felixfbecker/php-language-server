<?php
declare(strict_types = 1);

namespace LanguageServer\Tests;

use PHPUnit\Framework\TestCase;
use LanguageServer\Options;

class LanguageServerTest extends TestCase
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
}
