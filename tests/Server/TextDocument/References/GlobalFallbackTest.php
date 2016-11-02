<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument\References;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, LanguageClient, Project};
use LanguageServer\Protocol\{TextDocumentIdentifier, Position, ReferenceContext, Location, Range};
use LanguageServer\Tests\Server\ServerTestCase;

class GlobalFallbackTest extends ServerTestCase
{
    public function setUp()
    {
        $client = new LanguageClient(new MockProtocolStream, new MockProtocolStream);
        $project = new Project($client);
        $this->textDocument = new Server\TextDocument($project, $client);
        $project->openDocument('global_fallback', file_get_contents(__DIR__ . '/../../../../fixtures/global_fallback.php'));
        $project->openDocument('global_symbols', file_get_contents(__DIR__ . '/../../../../fixtures/global_symbols.php'));
    }

    public function testClassDoesNotFallback()
    {
        // class TestClass implements TestInterface
        // Get references for TestClass
        $result = $this->textDocument->references(new ReferenceContext, new TextDocumentIdentifier('global_symbols'), new Position(6, 9));
        $this->assertEquals([], $result);
    }

    public function testFallsBackForConstants()
    {
        // const TEST_CONST = 123;
        // Get references for TEST_CONST
        $result = $this->textDocument->references(new ReferenceContext, new TextDocumentIdentifier('global_symbols'), new Position(9, 13));
        $this->assertEquals([new Location('global_fallback', new Range(new Position(6, 5), new Position(6, 15)))], $result);
    }

    public function testFallsBackForFunctions()
    {
        // function test_function()
        // Get references for test_function
        $result = $this->textDocument->references(new ReferenceContext, new TextDocumentIdentifier('global_symbols'), new Position(78, 16));
        $this->assertEquals([new Location('global_fallback', new Range(new Position(5, 0), new Position(5, 13)))], $result);
    }
}
