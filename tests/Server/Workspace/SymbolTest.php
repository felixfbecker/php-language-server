<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\Workspace;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, Client, LanguageClient, Project, PhpDocument};
use LanguageServer\Protocol\{TextDocumentItem, TextDocumentIdentifier, SymbolKind, DiagnosticSeverity, FormattingOptions};
use AdvancedJsonRpc\{Request as RequestBody, Response as ResponseBody};
use function LanguageServer\pathToUri;

class SymbolTest extends TestCase
{
    /**
     * @var LanguageServer\Workspace $workspace
     */
    private $workspace;

    /**
     * @var string
     */
    private $symbolsUri;

    /**
     * @var string
     */
    private $referencesUri;

    public function setUp()
    {
        $client = new LanguageClient(new MockProtocolStream());
        $project = new Project($client);
        $this->workspace = new Server\Workspace($project, $client);
        $this->symbolsUri = pathToUri(realpath(__DIR__ . '/../../../fixtures/symbols.php'));
        $this->referencesUri = pathToUri(realpath(__DIR__ . '/../../../fixtures/references.php'));
        $project->loadDocument($this->symbolsUri);
        $project->loadDocument($this->referencesUri);
    }

    public function testEmptyQueryReturnsAllSymbols()
    {
        // Request symbols
        $result = $this->workspace->symbol('');
        $this->assertEquals([
            [
                'name' => 'TEST_CONST',
                'kind' => SymbolKind::CONSTANT,
                'location' => [
                    'uri' => $this->symbolsUri,
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
                    'uri' => $this->symbolsUri,
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
                    'uri' => $this->symbolsUri,
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
                    'uri' => $this->symbolsUri,
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
                    'uri' => $this->symbolsUri,
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
                    'uri' => $this->symbolsUri,
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
                    'uri' => $this->symbolsUri,
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
                    'uri' => $this->symbolsUri,
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
                    'uri' => $this->symbolsUri,
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
                    'uri' => $this->symbolsUri,
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
            ],
            [
                'name' => 'whatever',
                'kind' => SymbolKind::FUNCTION,
                'location' => [
                    'uri' => $this->referencesUri,
                    'range' => [
                        'start' => [
                            'line' => 15,
                            'character' => 0
                        ],
                        'end' => [
                            'line' => 17,
                            'character' => 1
                        ]
                    ]
                ],
                'containerName' => 'TestNamespace'
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testQueryFiltersResults()
    {
        // Request symbols
        $result = $this->workspace->symbol('testmethod');
        $this->assertEquals([
            [
                'name' => 'staticTestMethod',
                'kind' => SymbolKind::METHOD,
                'location' => [
                    'uri' => $this->symbolsUri,
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
                    'uri' => $this->symbolsUri,
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
            ]
        ], json_decode(json_encode($result), true));
    }
}
