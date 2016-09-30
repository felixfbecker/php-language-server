<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, Client, LanguageClient, Project, PhpDocument};
use LanguageServer\Protocol\{TextDocumentItem, TextDocumentIdentifier, SymbolKind, DiagnosticSeverity, FormattingOptions, VersionedTextDocumentIdentifier, TextDocumentContentChangeEvent, Range, Position};
use AdvancedJsonRpc\{Request as RequestBody, Response as ResponseBody};

class TextDocumentTest extends TestCase
{
    public function testDocumentSymbol()
    {
        $client = new LanguageClient(new MockProtocolStream());
        $project = new Project($client);
        $textDocument = new Server\TextDocument($project, $client);
        // Trigger parsing of source
        $textDocumentItem = new TextDocumentItem();
        $textDocumentItem->uri = 'whatever';
        $textDocumentItem->languageId = 'php';
        $textDocumentItem->version = 1;
        $textDocumentItem->text = file_get_contents(__DIR__ . '/../../fixtures/symbols.php');
        $textDocument->didOpen($textDocumentItem);
        // Request symbols
        $result = $textDocument->documentSymbol(new TextDocumentIdentifier('whatever'));
        $this->assertEquals([
            [
                'name' => 'TestNamespace',
                'kind' => SymbolKind::NAMESPACE,
                'location' => [
                    'uri' => 'whatever',
                    'range' => [
                        'start' => [
                            'line' => 2,
                            'character' => 0
                        ],
                        'end' => [
                            'line' => 2,
                            'character' => 24
                        ]
                    ]
                ],
                'containerName' => null
            ],
            [
                'name' => 'TestClass',
                'kind' => SymbolKind::CLASS_,
                'location' => [
                    'uri' => 'whatever',
                    'range' => [
                        'start' => [
                            'line' => 4,
                            'character' => 0
                        ],
                        'end' => [
                            'line' => 12,
                            'character' => 1
                        ]
                    ]
                ],
                'containerName' => 'TestNamespace'
            ],
            [
                'name' => 'testProperty',
                'kind' => SymbolKind::PROPERTY,
                'location' => [
                    'uri' => 'whatever',
                    'range' => [
                        'start' => [
                            'line' => 6,
                            'character' => 11
                        ],
                        'end' => [
                            'line' => 6,
                            'character' => 24
                        ]
                    ]
                ],
                'containerName' => 'TestNamespace\\TestClass'
            ],
            [
                'name' => 'testMethod',
                'kind' => SymbolKind::METHOD,
                'location' => [
                    'uri' => 'whatever',
                    'range' => [
                        'start' => [
                            'line' => 8,
                            'character' => 4
                        ],
                        'end' => [
                            'line' => 11,
                            'character' => 5
                        ]
                    ]
                ],
                'containerName' => 'TestNamespace\\TestClass'
            ],
            [
                'name' => 'TestTrait',
                'kind' => SymbolKind::CLASS_,
                'location' => [
                    'uri' => 'whatever',
                    'range' => [
                        'start' => [
                            'line' => 14,
                            'character' => 0
                        ],
                        'end' => [
                            'line' => 17,
                            'character' => 1
                        ]
                    ]
                ],
                'containerName' => 'TestNamespace'
            ],
            [
                'name' => 'TestInterface',
                'kind' => SymbolKind::INTERFACE,
                'location' => [
                    'uri' => 'whatever',
                    'range' => [
                        'start' => [
                            'line' => 19,
                            'character' => 0
                        ],
                        'end' => [
                            'line' => 22,
                            'character' => 1
                        ]
                    ]
                ],
                'containerName' => 'TestNamespace'
            ]
        ], json_decode(json_encode($result), true));
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
        $textDocumentItem->text = file_get_contents(__DIR__ . '/../../fixtures/invalid_file.php');
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

    public function testFormatting()
    {
        $client =  new LanguageClient(new MockProtocolStream());
        $project = new Project($client);
        $textDocument = new Server\TextDocument($project, $client);

        // Trigger parsing of source
        $textDocumentItem = new TextDocumentItem();
        $textDocumentItem->uri = 'whatever';
        $textDocumentItem->languageId = 'php';
        $textDocumentItem->version = 1;
        $textDocumentItem->text = file_get_contents(__DIR__ . '/../../fixtures/format.php');
        $textDocument->didOpen($textDocumentItem);

        // how code should look after formatting
        $expected = file_get_contents(__DIR__ . '/../../fixtures/format_expected.php');
        // Request formatting
        $result = $textDocument->formatting(new TextDocumentIdentifier('whatever'), new FormattingOptions());
        $this->assertEquals([0 => [
            'range' => [
                'start' => [
                    'line' => 0,
                    'character' => 0
                ],
                'end' => [
                    'line' => PHP_INT_MAX,
                    'character' => PHP_INT_MAX
                ]
            ],
            'newText' => $expected
        ]], json_decode(json_encode($result), true));
    }

    public function testDidChange()
    {
        $client =  new LanguageClient(new MockProtocolStream());
        $project = new Project($client);
        $textDocument = new Server\TextDocument($project, $client);

        $phpDocument = $project->getDocument('whatever');
        $phpDocument->updateContent("<?php\necho 'Hello, World'\n");

        $identifier = new VersionedTextDocumentIdentifier('whatever');
        $changeEvent = new TextDocumentContentChangeEvent();
        $changeEvent->range = new Range(new Position(0,0), new Position(9999,9999));
        $changeEvent->rangeLength = 9999;
        $changeEvent->text = "<?php\necho 'Goodbye, World'\n";

        $textDocument->didChange($identifier, [$changeEvent]);

        $this->assertEquals("<?php\necho 'Goodbye, World'\n", $phpDocument->getContent());
    }
}
