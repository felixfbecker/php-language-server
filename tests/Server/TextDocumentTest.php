<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, Client, LanguageClient};
use LanguageServer\Protocol\{TextDocumentItem, TextDocumentIdentifier, SymbolKind, DiagnosticSeverity};
use AdvancedJsonRpc\{Request as RequestBody, Response as ResponseBody};

class TextDocumentTest extends TestCase
{
    public function testDocumentSymbol()
    {
        $textDocument = new Server\TextDocument(new LanguageClient(new MockProtocolStream()));
        // Trigger parsing of source
        $textDocumentItem = new TextDocumentItem();
        $textDocumentItem->uri = 'whatever';
        $textDocumentItem->languageId = 'php';
        $textDocumentItem->version = 1;
        $textDocumentItem->text = file_get_contents(__DIR__ . '/../../fixtures/Symbols.php');
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
                            'character' => 23
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
                            'character' => 0
                        ]
                    ]
                ],
                'containerName' => null
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
                            'character' => 23
                        ]
                    ]
                ],
                'containerName' => 'TestClass'
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
                            'character' => 4
                        ]
                    ]
                ],
                'containerName' => null
            ],
            [
                'name' => 'testVariable',
                'kind' => SymbolKind::VARIABLE,
                'location' => [
                    'uri' => 'whatever',
                    'range' => [
                        'start' => [
                            'line' => 10,
                            'character' => 8
                        ],
                        'end' => [
                            'line' => 10,
                            'character' => 20
                        ]
                    ]
                ],
                'containerName' => null
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
                            'character' => 0
                        ]
                    ]
                ],
                'containerName' => null
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
                            'character' => 0
                        ]
                    ]
                ],
                'containerName' => null
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
        $textDocument = new Server\TextDocument($client);
        // Trigger parsing of source
        $textDocumentItem = new TextDocumentItem();
        $textDocumentItem->uri = 'whatever';
        $textDocumentItem->languageId = 'php';
        $textDocumentItem->version = 1;
        $textDocumentItem->text = file_get_contents(__DIR__ . '/../../fixtures/InvalidFile.php');
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
                        'character' => 14
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
