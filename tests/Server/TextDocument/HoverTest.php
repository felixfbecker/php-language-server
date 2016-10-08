<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\Tests\Server\ServerTestCase;
use LanguageServer\{Server, LanguageClient, Project};
use LanguageServer\Protocol\{TextDocumentIdentifier, Position, Range, Hover, MarkedString};
use function LanguageServer\pathToUri;

class HoverTest extends ServerTestCase
{
    public function testHoverForClassLike()
    {
        // $obj = new TestClass();
        // Get hover for TestClass
        $reference = $this->getReferenceLocations('TestClass')[0];
        $result = $this->textDocument->hover(new TextDocumentIdentifier($reference->uri), $reference->range->start);
        $this->assertEquals(new Hover([
            'Pariatur ut laborum tempor voluptate consequat ea deserunt.',
            new MarkedString('php', 'class TestClass implements \\TestInterface')
        ], $reference->range), $result);
    }

    public function testHoverWithoutDocBlock()
    {
        // echo $var;
        // Get hover for $var
        $uri = pathToUri(realpath(__DIR__ . '/../../../fixtures/references.php'));
        $result = $this->textDocument->hover(new TextDocumentIdentifier($uri), new Position(13, 7));
        $this->assertEquals(new Hover(
            [new MarkedString('php', '$var = 123;')],
            new Range(new Position(13, 5), new Position(13, 9))
        ), $result);
    }
}
