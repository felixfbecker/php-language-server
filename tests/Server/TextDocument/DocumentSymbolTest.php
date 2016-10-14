<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, LanguageClient, Project};
use LanguageServer\Protocol\{TextDocumentIdentifier, SymbolKind};

class DocumentSymbolTest extends TestCase
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
        $project->openDocument('symbols', file_get_contents(__DIR__ . '/../../../fixtures/symbols.php'));
    }

    public function test()
    {
        // Request symbols
        $result = $this->textDocument->documentSymbol(new TextDocumentIdentifier('symbols'));
        $this->assertEquals([
            [
                'name' => 'TEST_CONST',
                'kind' => SymbolKind::CONSTANT,
                'location' => [
                    'uri' => 'symbols',
                    'range' => [
                        'start' => [
                            'line' => 4,
                            'character' => 6
                        ],
                        'end' => [
                            'line' => 4,
                            'character' => 22
                        ]
                    ]
                ],
                'containerName' => 'TestNamespace'
            ],
            [
                'name' => 'TestClass',
                'kind' => SymbolKind::CLASS_,
                'location' => [
                    'uri' => 'symbols',
                    'range' => [
                        'start' => [
                            'line' => 6,
                            'character' => 0
                        ],
                        'end' => [
                            'line' => 21,
                            'character' => 1
                        ]
                    ]
                ],
                'containerName' => 'TestNamespace'
            ],
            [
                'name' => 'TEST_CLASS_CONST',
                'kind' => SymbolKind::CONSTANT,
                'location' => [
                    'uri' => 'symbols',
                    'range' => [
                        'start' => [
                            'line' => 8,
                            'character' => 10
                        ],
                        'end' => [
                            'line' => 8,
                            'character' => 32
                        ]
                    ]
                ],
                'containerName' => 'TestNamespace\\TestClass'
            ],
            [
                'name' => 'staticTestProperty',
                'kind' => SymbolKind::FIELD,
                'location' => [
                    'uri' => 'symbols',
                    'range' => [
                        'start' => [
                            'line' => 9,
                            'character' => 18
                        ],
                        'end' => [
                            'line' => 9,
                            'character' => 37
                        ]
                    ]
                ],
                'containerName' => 'TestNamespace\\TestClass'
            ],
            [
                'name' => 'testProperty',
                'kind' => SymbolKind::FIELD,
                'location' => [
                    'uri' => 'symbols',
                    'range' => [
                        'start' => [
                            'line' => 10,
                            'character' => 11
                        ],
                        'end' => [
                            'line' => 10,
                            'character' => 24
                        ]
                    ]
                ],
                'containerName' => 'TestNamespace\\TestClass'
            ],
            [
                'name' => 'staticTestMethod',
                'kind' => SymbolKind::METHOD,
                'location' => [
                    'uri' => 'symbols',
                    'range' => [
                        'start' => [
                            'line' => 12,
                            'character' => 4
                        ],
                        'end' => [
                            'line' => 15,
                            'character' => 5
                        ]
                    ]
                ],
                'containerName' => 'TestNamespace\\TestClass'
            ],
            [
                'name' => 'testMethod',
                'kind' => SymbolKind::METHOD,
                'location' => [
                    'uri' => 'symbols',
                    'range' => [
                        'start' => [
                            'line' => 17,
                            'character' => 4
                        ],
                        'end' => [
                            'line' => 20,
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
                    'uri' => 'symbols',
                    'range' => [
                        'start' => [
                            'line' => 23,
                            'character' => 0
                        ],
                        'end' => [
                            'line' => 26,
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
                    'uri' => 'symbols',
                    'range' => [
                        'start' => [
                            'line' => 28,
                            'character' => 0
                        ],
                        'end' => [
                            'line' => 31,
                            'character' => 1
                        ]
                    ]
                ],
                'containerName' => 'TestNamespace'
            ],
            [
                'name' => 'test_function',
                'kind' => SymbolKind::FUNCTION,
                'location' => [
                    'uri' => 'symbols',
                    'range' => [
                        'start' => [
                            'line' => 33,
                            'character' => 0
                        ],
                        'end' => [
                            'line' => 36,
                            'character' => 1
                        ]
                    ]
                ],
                'containerName' => 'TestNamespace'
            ]
        ], json_decode(json_encode($result), true));
    }
}
