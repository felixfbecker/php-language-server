<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, LanguageClient, Project};
use LanguageServer\Protocol\{Position, Location, Range};
use function LanguageServer\pathToUri;

abstract class ServerTestCase extends TestCase
{
    /**
     * @var Server\TextDocument
     */
    protected $textDocument;

    /**
     * @var Server\Workspace
     */
    protected $workspace;

    /**
     * @var Project
     */
    protected $project;

    /**
     * Map from FQN to Location of definition
     *
     * @var Location[]
     */
    private $definitionLocations;

    /**
     * Map from FQN to array of reference Locations
     *
     * @var Location[][]
     */
    private $referenceLocations;

    public function setUp()
    {
        $client             = new LanguageClient(new MockProtocolStream, new MockProtocolStream);
        $this->project      = new Project($client);
        $this->textDocument = new Server\TextDocument($this->project, $client);
        $this->workspace    = new Server\Workspace($this->project, $client);

        $globalSymbolsUri    = pathToUri(realpath(__DIR__ . '/../../fixtures/global_symbols.php'));
        $globalReferencesUri = pathToUri(realpath(__DIR__ . '/../../fixtures/global_references.php'));
        $symbolsUri          = pathToUri(realpath(__DIR__ . '/../../fixtures/symbols.php'));
        $referencesUri       = pathToUri(realpath(__DIR__ . '/../../fixtures/references.php'));
        $useUri              = pathToUri(realpath(__DIR__ . '/../../fixtures/use.php'));

        $this->project->loadDocument($symbolsUri);
        $this->project->loadDocument($referencesUri);
        $this->project->loadDocument($globalSymbolsUri);
        $this->project->loadDocument($globalReferencesUri);
        $this->project->loadDocument($useUri);

        // @codingStandardsIgnoreStart
        $this->definitionLocations = [

            // Global
            'TEST_CONST'                             => new Location($globalSymbolsUri,    new Range(new Position( 9,  6), new Position( 9, 22))),
            'TestClass'                              => new Location($globalSymbolsUri,    new Range(new Position(20,  0), new Position(61,  1))),
            'TestTrait'                              => new Location($globalSymbolsUri,    new Range(new Position(63,  0), new Position(66,  1))),
            'TestInterface'                          => new Location($globalSymbolsUri,    new Range(new Position(68,  0), new Position(71,  1))),
            'TestClass::TEST_CLASS_CONST'            => new Location($globalSymbolsUri,    new Range(new Position(27, 10), new Position(27, 32))),
            'TestClass::testProperty'                => new Location($globalSymbolsUri,    new Range(new Position(41, 11), new Position(41, 24))),
            'TestClass::staticTestProperty'          => new Location($globalSymbolsUri,    new Range(new Position(34, 18), new Position(34, 37))),
            'TestClass::staticTestMethod()'          => new Location($globalSymbolsUri,    new Range(new Position(46,  4), new Position(49,  5))),
            'TestClass::testMethod()'                => new Location($globalSymbolsUri,    new Range(new Position(57,  4), new Position(60,  5))),
            'test_function()'                        => new Location($globalSymbolsUri,    new Range(new Position(78,  0), new Position(81,  1))),
            'whatever()'                             => new Location($globalReferencesUri, new Range(new Position(21,  0), new Position(23,  1))),

            // Namespaced
            'TestNamespace\\TEST_CONST'                    => new Location($symbolsUri,    new Range(new Position( 9,  6), new Position( 9, 22))),
            'TestNamespace\\TestClass'                     => new Location($symbolsUri,    new Range(new Position(20,  0), new Position(61,  1))),
            'TestNamespace\\TestTrait'                     => new Location($symbolsUri,    new Range(new Position(63,  0), new Position(66,  1))),
            'TestNamespace\\TestInterface'                 => new Location($symbolsUri,    new Range(new Position(68,  0), new Position(71,  1))),
            'TestNamespace\\TestClass::TEST_CLASS_CONST'   => new Location($symbolsUri,    new Range(new Position(27, 10), new Position(27,  32))),
            'TestNamespace\\TestClass::testProperty'       => new Location($symbolsUri,    new Range(new Position(41, 11), new Position(41, 24))),
            'TestNamespace\\TestClass::staticTestProperty' => new Location($symbolsUri,    new Range(new Position(34, 18), new Position(34, 37))),
            'TestNamespace\\TestClass::staticTestMethod()' => new Location($symbolsUri,    new Range(new Position(46,  4), new Position(49,  5))),
            'TestNamespace\\TestClass::testMethod()'       => new Location($symbolsUri,    new Range(new Position(57,  4), new Position(60,  5))),
            'TestNamespace\\test_function()'               => new Location($symbolsUri,    new Range(new Position(78,  0), new Position(81,  1))),
            'TestNamespace\\whatever()'                    => new Location($referencesUri, new Range(new Position(21,  0), new Position(23,  1)))
        ];

        $this->referenceLocations = [

            // Namespaced
            'TestNamespace\\TEST_CONST' => [
                0 => new Location($referencesUri, new Range(new Position(29,  5), new Position(29, 15)))
            ],
            'TestNamespace\\TestClass' => [
                0 => new Location($referencesUri, new Range(new Position( 4, 11), new Position( 4, 20))), // $obj = new TestClass();
                1 => new Location($referencesUri, new Range(new Position( 7,  0), new Position( 7,  9))), // TestClass::staticTestMethod();
                2 => new Location($referencesUri, new Range(new Position( 8,  5), new Position( 8, 14))), // echo TestClass::$staticTestProperty;
                3 => new Location($referencesUri, new Range(new Position( 9,  5), new Position( 9, 14))), // TestClass::TEST_CLASS_CONST;
                4 => new Location($referencesUri, new Range(new Position(21, 18), new Position(21, 27))), // function whatever(TestClass $param)
                5 => new Location($referencesUri, new Range(new Position(21, 37), new Position(21, 46))), // function whatever(TestClass $param): TestClass
                6 => new Location($useUri,        new Range(new Position( 4,  4), new Position( 4, 27))), // use TestNamespace\TestClass;
            ],
            'TestNamespace\\TestInterface' => [
                0 => new Location($symbolsUri,    new Range(new Position(20, 27), new Position(20, 40))), // class TestClass implements TestInterface
                1 => new Location($symbolsUri,    new Range(new Position(57, 48), new Position(57, 61))), // public function testMethod($testParameter): TestInterface
                2 => new Location($referencesUri, new Range(new Position(33, 20), new Position(33, 33)))  // if ($abc instanceof TestInterface)
            ],
            'TestNamespace\\TestClass::TEST_CLASS_CONST' => [
                0 => new Location($symbolsUri,    new Range(new Position(48, 13), new Position(48, 35))), // echo self::TEST_CLASS_CONSTANT
                1 => new Location($referencesUri, new Range(new Position( 9,  5), new Position( 9, 32)))
            ],
            'TestNamespace\\TestClass::testProperty' => [
                0 => new Location($symbolsUri,    new Range(new Position(59,  8), new Position(59, 27))), // $this->testProperty = $testParameter;
                1 => new Location($referencesUri, new Range(new Position( 6,  5), new Position( 6, 23)))
            ],
            'TestNamespace\\TestClass::staticTestProperty' => [
                0 => new Location($referencesUri, new Range(new Position( 8,  5), new Position( 8, 35)))
            ],
            'TestNamespace\\TestClass::staticTestMethod()' => [
                0 => new Location($referencesUri, new Range(new Position( 7,  0), new Position( 7, 29)))
            ],
            'TestNamespace\\TestClass::testMethod()' => [
                0 => new Location($referencesUri, new Range(new Position( 5,  0), new Position( 5, 18)))
            ],
            'TestNamespace\\test_function()' => [
                0 => new Location($referencesUri, new Range(new Position(10,  0), new Position(10, 13))),
                1 => new Location($referencesUri, new Range(new Position(31, 13), new Position(31, 40)))
            ],

            // Global
            'TEST_CONST' => [
                0 => new Location($referencesUri,       new Range(new Position(29,  5), new Position(29, 15))),
                1 => new Location($globalReferencesUri, new Range(new Position(29,  5), new Position(29, 15)))
            ],
            'TestClass' => [
                0 => new Location($globalReferencesUri, new Range(new Position( 4, 11), new Position( 4, 20))), // $obj = new TestClass();
                1 => new Location($globalReferencesUri, new Range(new Position( 7,  0), new Position( 7,  9))), // TestClass::staticTestMethod();
                2 => new Location($globalReferencesUri, new Range(new Position( 8,  5), new Position( 8, 14))), // echo TestClass::$staticTestProperty;
                3 => new Location($globalReferencesUri, new Range(new Position( 9,  5), new Position( 9, 14))), // TestClass::TEST_CLASS_CONST;
                4 => new Location($globalReferencesUri, new Range(new Position(21, 18), new Position(21, 27))), // function whatever(TestClass $param)
                5 => new Location($globalReferencesUri, new Range(new Position(21, 37), new Position(21, 46))), // function whatever(TestClass $param): TestClass
            ],
            'TestInterface' => [
                0 => new Location($globalSymbolsUri,    new Range(new Position(20, 27), new Position(20, 40))), // class TestClass implements TestInterface
                1 => new Location($globalSymbolsUri,    new Range(new Position(57, 48), new Position(57, 61))), // public function testMethod($testParameter): TestInterface
                2 => new Location($globalReferencesUri, new Range(new Position(33, 20), new Position(33, 33)))  // if ($abc instanceof TestInterface)
            ],
            'TestClass::TEST_CLASS_CONST' => [
                0 => new Location($globalSymbolsUri,    new Range(new Position(48, 13), new Position(48, 35))), // echo self::TEST_CLASS_CONSTANT
                1 => new Location($globalReferencesUri, new Range(new Position( 9,  5), new Position( 9, 32)))
            ],
            'TestClass::testProperty' => [
                0 => new Location($globalSymbolsUri,    new Range(new Position(59,  8), new Position(59, 27))), // $this->testProperty = $testParameter;
                1 => new Location($globalReferencesUri, new Range(new Position( 6,  5), new Position( 6, 23)))
            ],
            'TestClass::staticTestProperty' => [
                0 => new Location($globalReferencesUri, new Range(new Position( 8,  5), new Position( 8, 35)))
            ],
            'TestClass::staticTestMethod()' => [
                0 => new Location($globalReferencesUri, new Range(new Position( 7,  0), new Position( 7, 29)))
            ],
            'TestClass::testMethod()' => [
                0 => new Location($globalReferencesUri, new Range(new Position( 5,  0), new Position( 5, 18)))
            ],
            'test_function()' => [
                0 => new Location($globalReferencesUri, new Range(new Position(10,  0), new Position(10, 13))),
                1 => new Location($globalReferencesUri, new Range(new Position(31, 13), new Position(31, 40)))
            ]
        ];
        // @codingStandardsIgnoreEnd
    }

    protected function getDefinitionLocation(string $fqn): Location
    {
        return $this->definitionLocations[$fqn];
    }

    protected function getReferenceLocations(string $fqn): array
    {
        return $this->referenceLocations[$fqn];
    }
}
