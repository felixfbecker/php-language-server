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
    
    public function testUriToPath()
    {
        $uri = 'file:///var/log';
        $this->assertEquals('/var/log', \LanguageServer\uriToPath($uri));
    
        $uri = 'file:///usr/local/bin';
        $this->assertEquals('/usr/local/bin', \LanguageServer\uriToPath($uri));
    
        $uri = 'file:///d/e/f';
        $this->assertEquals('/d/e/f', \LanguageServer\uriToPath($uri));
        
        $uri = 'file:///a/b/c/test.txt';
        $this->assertEquals('/a/b/c/test.txt', \LanguageServer\uriToPath($uri));
        
        $uri = 'file:///c%3A/foo/bar.baz';
        $this->assertEquals('c:\\foo\\bar.baz', \LanguageServer\uriToPath($uri));
        
        $uri = 'file:///c%3A/path/to/file/d%C3%BCr%C3%BCm+d%C3%B6ner.php';
        $this->assertEquals('c:\\path\\to\\file\\dürüm döner.php', \LanguageServer\uriToPath($uri));
    }
}
