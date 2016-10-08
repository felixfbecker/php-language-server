<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, Client, LanguageClient, Project};
use LanguageServer\Protocol\{TextDocumentIdentifier, TextDocumentItem, DiagnosticSeverity};

class ParseErrorsTest extends TestCase
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
    }

    public function testParseErrorsArePublishedAsDiagnostics()
    {
        $args = null;
        $client = new LanguageClient(new MockProtocolStream());
        $client->textDocument = new class($args) extends Client\TextDocument {
            private $args;
            public function __construct(&$args)
            {
                parent::__construct(new MockProtocolStream());
                $this->args = &$args;
            }
            public function publishDiagnostics(string $uri, array $diagnostics)
            {
                $this->args = func_get_args();
            }
        };

        $project = new Project($client);

        $textDocument = new Server\TextDocument($project, $client);

        // Trigger parsing of source
        $textDocumentItem = new TextDocumentItem();
        $textDocumentItem->uri = 'whatever';
        $textDocumentItem->languageId = 'php';
        $textDocumentItem->version = 1;
        $textDocumentItem->text = file_get_contents(__DIR__ . '/../../../fixtures/invalid_file.php');
        $textDocument->didOpen($textDocumentItem);
        $this->assertEquals([
            'whatever',
            [[
                'range' => [
                    'start' => [
                        'line' => 2,
                        'character' => 10
                    ],
                    'end' => [
                        'line' => 2,
                        'character' => 15
                    ]
                ],
                'severity' => DiagnosticSeverity::ERROR,
                'code' => null,
                'source' => 'php',
                'message' => "Syntax error, unexpected T_CLASS, expecting T_STRING"
            ]]
        ], json_decode(json_encode($args), true));
    }
}
