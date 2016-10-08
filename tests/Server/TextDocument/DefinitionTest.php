<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, LanguageClient, Project};
use LanguageServer\Protocol\{TextDocumentIdentifier, Position};

class DefinitionTest extends TestCase
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
        $project->getDocument('references')->updateContent(file_get_contents(__DIR__ . '/../../../fixtures/references.php'));
        $project->getDocument('symbols')->updateContent(file_get_contents(__DIR__ . '/../../../fixtures/symbols.php'));
        $project->getDocument('use')->updateContent(file_get_contents(__DIR__ . '/../../../fixtures/use.php'));
    }

    public function testDefinitionForClassLike()
    {
        // $obj = new TestClass();
        // Get definition for TestClass
        $result = $this->textDocument->definition(new TextDocumentIdentifier('references'), new Position(4, 16));
        $this->assertEquals([
            'uri' => 'symbols',
            'range' => [
                'start' => [
                    'line' => 6,
                    'character' => 0
                ],
                'end' => [
                    'line' => 21,
                    'character' => 0
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testDefinitionForClassLikeUseStatement()
    {
        // use TestNamespace\TestClass;
        // Get definition for TestClass
        $result = $this->textDocument->definition(new TextDocumentIdentifier('use'), new Position(4, 22));
        $this->assertEquals([
            'uri' => 'symbols',
            'range' => [
                'start' => [
                    'line' => 6,
                    'character' => 0
                ],
                'end' => [
                    'line' => 21,
                    'character' => 0
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testDefinitionForClassLikeGroupUseStatement()
    {
        // use TestNamespace\{TestTrait, TestInterface};
        // Get definition for TestInterface
        $result = $this->textDocument->definition(new TextDocumentIdentifier('use'), new Position(5, 37));
        $this->assertEquals([
            'uri' => 'symbols',
            'range' => [
                'start' => [
                    'line' => 28,
                    'character' => 0
                ],
                'end' => [
                    'line' => 31,
                    'character' => 0
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testDefinitionForImplements()
    {
        // class TestClass implements TestInterface
        // Get definition for TestInterface
        $result = $this->textDocument->definition(new TextDocumentIdentifier('symbols'), new Position(6, 33));
        $this->assertEquals([
            'uri' => 'symbols',
            'range' => [
                'start' => [
                    'line' => 28,
                    'character' => 0
                ],
                'end' => [
                    'line' => 31,
                    'character' => 0
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testDefinitionForClassConstants()
    {
        // echo TestClass::TEST_CLASS_CONST;
        // Get definition for TEST_CLASS_CONST
        $result = $this->textDocument->definition(new TextDocumentIdentifier('references'), new Position(9, 21));
        $this->assertEquals([
            'uri' => 'symbols',
            'range' => [
                'start' => [
                    'line' => 8,
                    'character' => 10
                ],
                'end' => [
                    'line' => 8,
                    'character' => 31
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testDefinitionForConstants()
    {
        // echo TEST_CONST;
        // Get definition for TEST_CONST
        $result = $this->textDocument->definition(new TextDocumentIdentifier('references'), new Position(23, 9));
        $this->assertEquals([
            'uri' => 'symbols',
            'range' => [
                'start' => [
                    'line' => 4,
                    'character' => 6
                ],
                'end' => [
                    'line' => 4,
                    'character' => 21
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testDefinitionForStaticMethods()
    {
        // TestClass::staticTestMethod();
        // Get definition for staticTestMethod
        $result = $this->textDocument->definition(new TextDocumentIdentifier('references'), new Position(7, 20));
        $this->assertEquals([
            'uri' => 'symbols',
            'range' => [
                'start' => [
                    'line' => 12,
                    'character' => 4
                ],
                'end' => [
                    'line' => 15,
                    'character' => 4
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testDefinitionForStaticProperties()
    {
        // echo TestClass::$staticTestProperty;
        // Get definition for staticTestProperty
        $result = $this->textDocument->definition(new TextDocumentIdentifier('references'), new Position(8, 25));
        $this->assertEquals([
            'uri' => 'symbols',
            'range' => [
                'start' => [
                    'line' => 9,
                    'character' => 18
                ],
                'end' => [
                    'line' => 9,
                    'character' => 36
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testDefinitionForMethods()
    {
        // $obj->testMethod();
        // Get definition for testMethod
        $result = $this->textDocument->definition(new TextDocumentIdentifier('references'), new Position(5, 11));
        $this->assertEquals([
            'uri' => 'symbols',
            'range' => [
                'start' => [
                    'line' => 17,
                    'character' => 4
                ],
                'end' => [
                    'line' => 20,
                    'character' => 4
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testDefinitionForProperties()
    {
        // echo $obj->testProperty;
        // Get definition for testProperty
        $result = $this->textDocument->definition(new TextDocumentIdentifier('references'), new Position(6, 18));
        $this->assertEquals([
            'uri' => 'symbols',
            'range' => [
                'start' => [
                    'line' => 10,
                    'character' => 11
                ],
                'end' => [
                    'line' => 10,
                    'character' => 23
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testDefinitionForVariables()
    {
        // echo $var;
        // Get definition for $var
        $result = $this->textDocument->definition(new TextDocumentIdentifier('references'), new Position(13, 7));
        $this->assertEquals([
            'uri' => 'references',
            'range' => [
                'start' => [
                    'line' => 12,
                    'character' => 0
                ],
                'end' => [
                    'line' => 12,
                    'character' => 9
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testDefinitionForParamTypeHints()
    {
        // function whatever(TestClass $param) {
        // Get definition for TestClass
        $result = $this->textDocument->definition(new TextDocumentIdentifier('references'), new Position(15, 23));
        $this->assertEquals([
            'uri' => 'symbols',
            'range' => [
                'start' => [
                    'line' => 6,
                    'character' => 0
                ],
                'end' => [
                    'line' => 21,
                    'character' => 0
                ]
            ]
        ], json_decode(json_encode($result), true));
    }
    public function testDefinitionForReturnTypeHints()
    {
        // function whatever(TestClass $param) {
        // Get definition for TestClass
        $result = $this->textDocument->definition(new TextDocumentIdentifier('references'), new Position(15, 42));
        $this->assertEquals([
            'uri' => 'symbols',
            'range' => [
                'start' => [
                    'line' => 6,
                    'character' => 0
                ],
                'end' => [
                    'line' => 21,
                    'character' => 0
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testDefinitionForParams()
    {
        // echo $param;
        // Get definition for $param
        $result = $this->textDocument->definition(new TextDocumentIdentifier('references'), new Position(16, 13));
        $this->assertEquals([
            'uri' => 'references',
            'range' => [
                'start' => [
                    'line' => 15,
                    'character' => 18
                ],
                'end' => [
                    'line' => 15,
                    'character' => 33
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testDefinitionForUsedVariables()
    {
        // echo $var;
        // Get definition for $var
        $result = $this->textDocument->definition(new TextDocumentIdentifier('references'), new Position(20, 11));
        $this->assertEquals([
            'uri' => 'references',
            'range' => [
                'start' => [
                    'line' => 19,
                    'character' => 22
                ],
                'end' => [
                    'line' => 19,
                    'character' => 25
                ]
            ]
        ], json_decode(json_encode($result), true));
    }

    public function testDefinitionForFunctions()
    {
        // test_function();
        // Get definition for test_function
        $result = $this->textDocument->definition(new TextDocumentIdentifier('references'), new Position(10, 4));
        $this->assertEquals([
            'uri' => 'symbols',
            'range' => [
                'start' => [
                    'line' => 33,
                    'character' => 0
                ],
                'end' => [
                    'line' => 36,
                    'character' => 0
                ]
            ]
        ], json_decode(json_encode($result), true));
    }
}
