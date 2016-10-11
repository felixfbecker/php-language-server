<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument\References;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, LanguageClient, Project};
use LanguageServer\Protocol\{TextDocumentIdentifier, Position, ReferenceContext};
use function LanguageServer\pathToUri;

class NamespacedTest extends TestCase
{
    /**
     * @var Server\TextDocument
     */
    private $textDocument;

    private $symbolsUri;
    private $referencesUri;
    private $useUri;

    public function setUp()
    {
        $client = new LanguageClient(new MockProtocolStream());
        $project = new Project($client);
        $this->textDocument = new Server\TextDocument($project, $client);
        $this->symbolsUri = pathToUri(realpath(__DIR__ . '/../../../../fixtures/symbols.php'));
        $this->referencesUri = pathToUri(realpath(__DIR__ . '/../../../../fixtures/references.php'));
        $this->useUri = pathToUri(realpath(__DIR__ . '/../../../../fixtures/use.php'));
        $project->loadDocument($this->referencesUri, file_get_contents($this->referencesUri));
        $project->loadDocument($this->symbolsUri, file_get_contents($this->symbolsUri));
        $project->loadDocument($this->useUri, file_get_contents($this->useUri));
    }

    public function testReferencesForClassLike()
    {
        // class TestClass implements TestInterface
        // Get references for TestClass
        $result = $this->textDocument->references(new ReferenceContext, new TextDocumentIdentifier($this->symbolsUri), new Position(6, 9));
        $this->assertEquals([
            // $obj = new TestClass();
            [
                'uri' => $this->referencesUri,
                'range' => [
                    'start' => [
                        'line' => 4,
                        'character' => 11
                    ],
                    'end' => [
                        'line' => 4,
                        'character' => 20
                    ]
                ]
            ],
            // TestClass::staticTestMethod();
            [
                'uri' => $this->referencesUri,
                'range' => [
                    'start' => [
                        'line' => 7,
                        'character' => 0
                    ],
                    'end' => [
                        'line' => 7,
                        'character' => 9
                    ]
                ]
            ],
            // echo TestClass::$staticTestProperty;
            [
                'uri' => $this->referencesUri,
                'range' => [
                    'start' => [
                        'line' => 8,
                        'character' => 5
                    ],
                    'end' => [
                        'line' => 8,
                        'character' => 14
                    ]
                ]
            ],
            // TestClass::TEST_CLASS_CONST;
            [
                'uri' => $this->referencesUri,
                'range' => [
                    'start' => [
                        'line' => 9,
                        'character' => 5
                    ],
                    'end' => [
                        'line' => 9,
                        'character' => 14
                    ]
                ]
            ],
            // function whatever(TestClass $param)
            [
                'uri' => $this->referencesUri,
                'range' => [
                    'start' => [
                        'line' => 15,
                        'character' => 18
                    ],
                    'end' => [
                        'line' => 15,
                        'character' => 27
                    ]
                ]
            ],
            // function whatever(TestClass $param): TestClass
            [
                'uri' => $this->referencesUri,
                'range' => [
                    'start' => [
                        'line' => 15,
                        'character' => 37
                    ],
                    'end' => [
                        'line' => 15,
                        'character' => 46
                    ]
                ]
            ],
            // use TestNamespace\TestClass;
            [
                'uri' => $this->useUri,
                'range' => [
                    'start' => [
                        'line' => 4,
                        'character' => 4
                    ],
                    'end' => [
                        'line' => 4,
                        'character' => 27
                    ]
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testReferencesForClassConstants()
    {
        // const TEST_CLASS_CONST = 123;
        // Get references for TEST_CLASS_CONST
        $result = $this->textDocument->references(new ReferenceContext, new TextDocumentIdentifier($this->symbolsUri), new Position(8, 19));
        $this->assertEquals([
            [
                'uri' => $this->referencesUri,
                'range' => [
                    'start' => [
                        'line' => 9,
                        'character' => 5
                    ],
                    'end' => [
                        'line' => 9,
                        'character' => 32
                    ]
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testReferencesForConstants()
    {
        // const TEST_CONST = 123;
        // Get references for TEST_CONST
        $result = $this->textDocument->references(new ReferenceContext, new TextDocumentIdentifier($this->symbolsUri), new Position(4, 13));
        $this->assertEquals([
            [
                'uri' => $this->referencesUri,
                'range' => [
                    'start' => [
                        'line' => 23,
                        'character' => 5
                    ],
                    'end' => [
                        'line' => 23,
                        'character' => 15
                    ]
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testReferencesForStaticMethods()
    {
        // public static function staticTestMethod()
        // Get references for staticTestMethod
        $result = $this->textDocument->references(new ReferenceContext, new TextDocumentIdentifier($this->symbolsUri), new Position(12, 35));
        $this->assertEquals([
            [
                'uri' => $this->referencesUri,
                'range' => [
                    'start' => [
                        'line' => 7,
                        'character' => 0
                    ],
                    'end' => [
                        'line' => 7,
                        'character' => 29
                    ]
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testReferencesForStaticProperties()
    {
        // public static $staticTestProperty;
        // Get references for $staticTestProperty
        $result = $this->textDocument->references(new ReferenceContext, new TextDocumentIdentifier($this->symbolsUri), new Position(9, 27));
        $this->assertEquals([
            [
                'uri' => $this->referencesUri,
                'range' => [
                    'start' => [
                        'line' => 8,
                        'character' => 5
                    ],
                    'end' => [
                        'line' => 8,
                        'character' => 35
                    ]
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testReferencesForMethods()
    {
        // public function testMethod($testParameter)
        // Get references for testMethod
        $result = $this->textDocument->references(new ReferenceContext, new TextDocumentIdentifier($this->symbolsUri), new Position(17, 24));
        $this->assertEquals([
            [
                'uri' => $this->referencesUri,
                'range' => [
                    'start' => [
                        'line' => 5,
                        'character' => 0
                    ],
                    'end' => [
                        'line' => 5,
                        'character' => 18
                    ]
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testReferencesForProperties()
    {
        // public $testProperty;
        // Get references for testProperty
        $result = $this->textDocument->references(new ReferenceContext, new TextDocumentIdentifier($this->symbolsUri), new Position(10, 15));
        $this->assertEquals([
            [
                'uri' => $this->referencesUri,
                'range' => [
                    'start' => [
                        'line' => 6,
                        'character' => 5
                    ],
                    'end' => [
                        'line' => 6,
                        'character' => 23
                    ]
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testReferencesForVariables()
    {
        // $var = 123;
        // Get definition for $var
        $result = $this->textDocument->references(new ReferenceContext, new TextDocumentIdentifier($this->referencesUri), new Position(13, 7));
        $this->assertEquals([
            [
                'uri' => $this->referencesUri,
                'range' => [
                    'start' => [
                        'line' => 12,
                        'character' => 0
                    ],
                    'end' => [
                        'line' => 12,
                        'character' => 4
                    ]
                ]
            ],
            [
                'uri' => $this->referencesUri,
                'range' => [
                    'start' => [
                        'line' => 13,
                        'character' => 5
                    ],
                    'end' => [
                        'line' => 13,
                        'character' => 9
                    ]
                ]
            ],
            [
                'uri' => $this->referencesUri,
                'range' => [
                    'start' => [
                        'line' => 20,
                        'character' => 9
                    ],
                    'end' => [
                        'line' => 20,
                        'character' => 13
                    ]
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testReferencesForFunctionParams()
    {
        // function whatever(TestClass $param): TestClass
        // Get references for $param
        $result = $this->textDocument->references(new ReferenceContext, new TextDocumentIdentifier($this->referencesUri), new Position(15, 32));
        $this->assertEquals([
            [
                'uri' => $this->referencesUri,
                'range' => [
                    'start' => [
                        'line' => 16,
                        'character' => 9
                    ],
                    'end' => [
                        'line' => 16,
                        'character' => 15
                    ]
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testReferencesForFunctions()
    {
        // function test_function()
        // Get references for test_function
        $result = $this->textDocument->references(new ReferenceContext, new TextDocumentIdentifier($this->symbolsUri), new Position(33, 16));
        $this->assertEquals([
            [
                'uri' => $this->referencesUri,
                'range' => [
                    'start' => [
                        'line' => 10,
                        'character' => 0
                    ],
                    'end' => [
                        'line' => 10,
                        'character' => 13
                    ]
                ]
            ]
        ], json_decode(json_encode($result), true));
    }
}
