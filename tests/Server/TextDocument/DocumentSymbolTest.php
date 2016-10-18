<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use LanguageServer\Tests\Server\TextDocument\TextDocumentTestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, LanguageClient, Project};
use LanguageServer\Protocol\{TextDocumentIdentifier, SymbolInformation, SymbolKind, Position, Location, Range};
use function LanguageServer\pathToUri;

class DocumentSymbolTest extends TextDocumentTestCase
{
    public function test()
    {
        // Request symbols
        $uri = pathToUri(realpath(__DIR__ . '/../../../fixtures/symbols.php'));
        $result = $this->textDocument->documentSymbol(new TextDocumentIdentifier($uri));
        $this->assertEquals([
            new SymbolInformation('TEST_CONST',         SymbolKind::CONSTANT,  new Location($uri, new Range(new Position( 4,  6), new Position( 4, 22))), 'TestNamespace'),
            new SymbolInformation('TestClass',          SymbolKind::CLASS_,    new Location($uri, new Range(new Position( 6,  0), new Position(21,  1))), 'TestNamespace'),
            new SymbolInformation('TEST_CLASS_CONST',   SymbolKind::CONSTANT,  new Location($uri, new Range(new Position( 8, 10), new Position( 8, 32))), 'TestNamespace\\TestClass'),
            new SymbolInformation('staticTestProperty', SymbolKind::FIELD,     new Location($uri, new Range(new Position( 9, 18), new Position( 9, 37))), 'TestNamespace\\TestClass'),
            new SymbolInformation('testProperty',       SymbolKind::FIELD,     new Location($uri, new Range(new Position(10, 11), new Position(10, 24))), 'TestNamespace\\TestClass'),
            new SymbolInformation('staticTestMethod',   SymbolKind::METHOD,    new Location($uri, new Range(new Position(12,  4), new Position(15,  5))), 'TestNamespace\\TestClass'),
            new SymbolInformation('testMethod',         SymbolKind::METHOD,    new Location($uri, new Range(new Position(17,  4), new Position(20,  5))), 'TestNamespace\\TestClass'),
            new SymbolInformation('TestTrait',          SymbolKind::CLASS_,    new Location($uri, new Range(new Position(23,  0), new Position(26,  1))), 'TestNamespace'),
            new SymbolInformation('TestInterface',      SymbolKind::INTERFACE, new Location($uri, new Range(new Position(28,  0), new Position(31,  1))), 'TestNamespace'),
            new SymbolInformation('test_function',      SymbolKind::FUNCTION,  new Location($uri, new Range(new Position(33,  0), new Position(36,  1))), 'TestNamespace')
        ], $result);
    }
}
