<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument\References;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, LanguageClient, Project};
use LanguageServer\Protocol\{TextDocumentIdentifier, Position, ReferenceContext};

class GlobalFallbackTest extends TestCase
{
    /**
     * @var Server\TextDocument
     */
    private $textDocument;

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
        // class TestClass implements TestInterface
        // Get references for TestClass
        $result = $this->textDocument->references(new ReferenceContext, new TextDocumentIdentifier('global_symbols'), new Position(6, 9));
        $this->assertEquals([], $result);
    }

    public function testFallsBackForConstants()
    {
        // const TEST_CONST = 123;
        // Get references for TEST_CONST
        $result = $this->textDocument->references(new ReferenceContext, new TextDocumentIdentifier('global_symbols'), new Position(4, 13));
        $this->assertEquals([
            [
                'uri' => 'global_fallback',
                'range' => [
                    'start' => [
                        'line' => 6,
                        'character' => 5
                    ],
                    'end' => [
                        'line' => 6,
                        'character' => 15
                    ]
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testFallsBackForFunctions()
    {
        // function test_function()
        // Get references for test_function
        $result = $this->textDocument->references(new ReferenceContext, new TextDocumentIdentifier('global_symbols'), new Position(33, 16));
        $this->assertEquals([
            [
                'uri' => 'global_fallback',
                'range' => [
                    'start' => [
                        'line' => 5,
                        'character' => 0
                    ],
                    'end' => [
                        'line' => 5,
                        'character' => 13
                    ]
                ]
            ]
        ], json_decode(json_encode($result), true));
    }
}
