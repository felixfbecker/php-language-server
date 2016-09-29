<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Utils;

use PHPUnit\Framework\TestCase;

class FileUriTest extends TestCase
{
    public function testSpecialCharsAreEscaped()
    {
        $uri = \LanguageServer\pathToUri('c:/path/to/file/dürüm döner.php');
        $this->assertEquals('file:///c%3A/path/to/file/d%C3%BCr%C3%BCm+d%C3%B6ner.php', $uri);
    }

    public function testUriIsWellFormed()
    {
        $uri = \LanguageServer\pathToUri('var/log');
        $this->assertEquals('file:///var/log', $uri);

        $uri = \LanguageServer\pathToUri('/usr/local/bin');
        $this->assertEquals('file:///usr/local/bin', $uri);

        $uri = \LanguageServer\pathToUri('a/b/c/test.txt');
        $this->assertEquals('file:///a/b/c/test.txt', $uri);

        $uri = \LanguageServer\pathToUri('/d/e/f');
        $this->assertEquals('file:///d/e/f', $uri);
    }

    public function testBackslashesAreTransformed()
    {
        $uri = \LanguageServer\pathToUri('c:\\foo\\bar.baz');
        $this->assertEquals('file:///c%3A/foo/bar.baz', $uri);
    }
}
