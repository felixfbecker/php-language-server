<?php
declare(strict_types=1);

namespace LanguageServer\Tests\Server\TextDocument\References;

use Amp\Loop;
use LanguageServer\{
    LanguageClient, PhpDocumentLoader, Server, DefinitionResolver
};
use LanguageServer\ContentRetriever\FileSystemContentRetriever;
use LanguageServer\Index\{
    DependenciesIndex, Index, ProjectIndex
};
use LanguageServerProtocol\{
    Location, Position, Range, ReferenceContext, TextDocumentIdentifier
};
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\Tests\Server\ServerTestCase;

class GlobalFallbackTest extends ServerTestCase
{
    public function setUp()
    {
        $projectIndex = new ProjectIndex(new Index, new DependenciesIndex);
        $projectIndex->setComplete();
        $definitionResolver = new DefinitionResolver($projectIndex);
        $client = new LanguageClient(new MockProtocolStream, new MockProtocolStream);
        $this->documentLoader = new PhpDocumentLoader(new FileSystemContentRetriever, $projectIndex, $definitionResolver);
        $this->textDocument = new Server\TextDocument($this->documentLoader, $definitionResolver, $client, $projectIndex);
        $this->documentLoader->open('global_fallback', file_get_contents(__DIR__ . '/../../../../fixtures/global_fallback.php'));
        $this->documentLoader->open('global_symbols', file_get_contents(__DIR__ . '/../../../../fixtures/global_symbols.php'));
    }

    public function testClassDoesNotFallback()
    {
        Loop::run(function () {
            // class TestClass implements TestInterface
            // Get references for TestClass
            $result = yield $this->textDocument->references(
                new ReferenceContext,
                new TextDocumentIdentifier('global_symbols'),
                new Position(6, 9)
            );
            $this->assertEquals([], $result);
        });
    }

    public function testFallsBackForConstants()
    {
        Loop::run(function () {
            // const TEST_CONST = 123;
            // Get references for TEST_CONST
            $result = yield $this->textDocument->references(
                new ReferenceContext,
                new TextDocumentIdentifier('global_symbols'),
                new Position(9, 13)
            );
            $this->assertEquals([new Location('global_fallback', new Range(new Position(6, 5), new Position(6, 15)))], $result);
        });
    }

    public function testFallsBackForFunctions()
    {
        Loop::run(function () {
            // function test_function()
            // Get references for test_function
            $result = yield $this->textDocument->references(
                new ReferenceContext,
                new TextDocumentIdentifier('global_symbols'),
                new Position(78, 16)
            );
            $this->assertEquals([new Location('global_fallback', new Range(new Position(5, 0), new Position(5, 13)))], $result);
        });
    }
}
