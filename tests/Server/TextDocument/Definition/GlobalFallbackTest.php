<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument\Definition;

use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\Tests\Server\ServerTestCase;
use LanguageServer\{Server, LanguageClient, Project};
use LanguageServer\Protocol\{TextDocumentIdentifier, Position, Range, Location};

class GlobalFallbackTest extends ServerTestCase
{
    public function setUp()
    {
        $client = new LanguageClient(new MockProtocolStream());
        $project = new Project($client);
        $this->textDocument = new Server\TextDocument($project, $client);
        $project->openDocument('global_fallback', file_get_contents(__DIR__ . '/../../../../fixtures/global_fallback.php'));
        $project->openDocument('global_symbols', file_get_contents(__DIR__ . '/../../../../fixtures/global_symbols.php'));
    }

    public function testClassDoesNotFallback()
    {
        // $obj = new TestClass();
        // Get definition for TestClass should not fall back to global
        $result = $this->textDocument->definition(new TextDocumentIdentifier('global_fallback'), new Position(9, 16));
        $this->assertEquals([], $result);
    }

    public function testFallsBackForConstants()
    {
        // echo TEST_CONST;
        // Get definition for TEST_CONST
        $result = $this->textDocument->definition(new TextDocumentIdentifier('global_fallback'), new Position(6, 10));
        $this->assertEquals(new Location('global_symbols', new Range(new Position(9, 6), new Position(9, 22))), $result);
    }

    public function testFallsBackForFunctions()
    {
        // test_function();
        // Get definition for test_function
        $result = $this->textDocument->definition(new TextDocumentIdentifier('global_fallback'), new Position(5, 6));
        $this->assertEquals(new Location('global_symbols', new Range(new Position(78, 0), new Position(81, 1))), $result);
    }
}
