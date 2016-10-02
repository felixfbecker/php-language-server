<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Utils;

use PHPUnit\Framework\TestCase;

class RecursiveFileSearchTest extends TestCase
{
    public function testFilesAreFound()
    {
        $path = realpath(__DIR__ . '/../../fixtures/recursive');
        $files = \LanguageServer\findFilesRecursive($path, '/.+\.txt/');
        sort($files);
        $this->assertEquals([
            $path . DIRECTORY_SEPARATOR . 'a.txt',
            $path . DIRECTORY_SEPARATOR . 'search' . DIRECTORY_SEPARATOR . 'b.txt',
            $path . DIRECTORY_SEPARATOR . 'search' . DIRECTORY_SEPARATOR . 'here' . DIRECTORY_SEPARATOR . 'c.txt',
        ], $files);
    }
}
