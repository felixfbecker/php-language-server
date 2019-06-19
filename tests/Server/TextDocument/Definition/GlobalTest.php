<?php
declare(strict_types=1);

namespace LanguageServer\Tests\Server\TextDocument\Definition;

use Amp\Loop;
use LanguageServer\Tests\Server\ServerTestCase;
use LanguageServerProtocol\{TextDocumentIdentifier, Position, Location, Range};
use function LanguageServer\pathToUri;

class GlobalTest extends ServerTestCase
{
    public function testDefinitionFileBeginning()
    {
        Loop::run(function () {
            // |<?php
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier(pathToUri(realpath(__DIR__ . '/../../../../fixtures/references.php'))),
                new Position(0, 0)
            );
            $this->assertEquals([], $result);
        });
    }

    public function testDefinitionEmptyResult()
    {
        Loop::run(function () {
            // namespace keyword
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier(pathToUri(realpath(__DIR__ . '/../../../../fixtures/references.php'))),
                new Position(1, 0)
            );
            $this->assertEquals([], $result);
        });
    }

    public function testDefinitionForSelfKeyword()
    {
        Loop::run(function () {
            // echo self::TEST_CLASS_CONST;
            // Get definition for self
            $reference = $this->getReferenceLocations('TestClass')[0];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->start
            );
            $this->assertEquals($this->getDefinitionLocation('TestClass'), $result);
        });
    }

    public function testDefinitionForClassLike()
    {
        Loop::run(function () {
            // $obj = new TestClass();
            // Get definition for TestClass
            $reference = $this->getReferenceLocations('TestClass')[1];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->start
            );
            $this->assertEquals($this->getDefinitionLocation('TestClass'), $result);
        });
    }

    public function testDefinitionForClassOnStaticMethodCall()
    {
        Loop::run(function () {
            // TestClass::staticTestMethod();
            // Get definition for TestClass
            $reference = $this->getReferenceLocations('TestClass')[2];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->start
            );
            $this->assertEquals($this->getDefinitionLocation('TestClass'), $result);
        });
    }

    public function testDefinitionForClassOnStaticPropertyFetch()
    {
        Loop::run(function () {
            // echo TestClass::$staticTestProperty;
            // Get definition for TestClass
            $reference = $this->getReferenceLocations('TestClass')[3];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->start
            );
            $this->assertEquals($this->getDefinitionLocation('TestClass'), $result);
        });
    }

    public function testDefinitionForClassOnConstFetch()
    {
        Loop::run(function () {
            // TestClass::TEST_CLASS_CONST;
            // Get definition for TestClass
            $reference = $this->getReferenceLocations('TestClass')[4];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->start
            );
            $this->assertEquals($this->getDefinitionLocation('TestClass'), $result);
        });
    }

    public function testDefinitionForImplements()
    {
        Loop::run(function () {
            // class TestClass implements TestInterface
            // Get definition for TestInterface
            $reference = $this->getReferenceLocations('TestInterface')[0];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->start
            );
            $this->assertEquals($this->getDefinitionLocation('TestInterface'), $result);
        });
    }

    public function testDefinitionForClassConstants()
    {
        Loop::run(function () {
            // echo TestClass::TEST_CLASS_CONST;
            // Get definition for TEST_CLASS_CONST
            $reference = $this->getReferenceLocations('TestClass::TEST_CLASS_CONST')[1];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->end
            );
            $this->assertEquals($this->getDefinitionLocation('TestClass::TEST_CLASS_CONST'), $result);
        });
    }

    public function testDefinitionForClassConstantsOnSelf()
    {
        Loop::run(function () {
            // echo self::TEST_CLASS_CONST;
            // Get definition for TEST_CLASS_CONST
            $reference = $this->getReferenceLocations('TestClass::TEST_CLASS_CONST')[0];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->end
            );
            $this->assertEquals($this->getDefinitionLocation('TestClass::TEST_CLASS_CONST'), $result);
        });
    }

    public function testDefinitionForConstants()
    {
        Loop::run(function () {
            // echo TEST_CONST;
            // Get definition for TEST_CONST
            $reference = $this->getReferenceLocations('TEST_CONST')[1];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->start
            );
            $this->assertEquals($this->getDefinitionLocation('TEST_CONST'), $result);
        });
    }

    public function testDefinitionForStaticMethods()
    {
        Loop::run(function () {
            // TestClass::staticTestMethod();
            // Get definition for staticTestMethod
            $reference = $this->getReferenceLocations('TestClass::staticTestMethod()')[0];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->end
            );
            $this->assertEquals($this->getDefinitionLocation('TestClass::staticTestMethod()'), $result);
        });
    }

    public function testDefinitionForStaticProperties()
    {
        Loop::run(function () {
            // echo TestClass::$staticTestProperty;
            // Get definition for staticTestProperty
            $reference = $this->getReferenceLocations('TestClass::staticTestProperty')[0];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->end
            );
            $this->assertEquals($this->getDefinitionLocation('TestClass::staticTestProperty'), $result);
        });
    }

    public function testDefinitionForMethods()
    {
        Loop::run(function () {
            // $obj->testMethod();
            // Get definition for testMethod
            $reference = $this->getReferenceLocations('TestClass::testMethod()')[0];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->end
            );
            $this->assertEquals($this->getDefinitionLocation('TestClass::testMethod()'), $result);
        });
    }

    public function testDefinitionForMethodOnChildClass()
    {
        Loop::run(function () {
            // $child->testMethod();
            // Get definition for testMethod
            $reference = $this->getReferenceLocations('TestClass::testMethod()')[2];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->end
            );
            $this->assertEquals($this->getDefinitionLocation('TestClass::testMethod()'), $result);
        });
    }

    public function testDefinitionForProperties()
    {
        Loop::run(function () {
            // echo $obj->testProperty;
            // Get definition for testProperty
            $reference = $this->getReferenceLocations('TestClass::testProperty')[1];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->end
            );
            $this->assertEquals($this->getDefinitionLocation('TestClass::testProperty'), $result);
        });
    }

    public function testDefinitionForPropertiesOnThis()
    {
        Loop::run(function () {
            // $this->testProperty = $testParameter;
            // Get definition for testProperty
            $reference = $this->getReferenceLocations('TestClass::testProperty')[0];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->end
            );
            $this->assertEquals($this->getDefinitionLocation('TestClass::testProperty'), $result);
        });
    }

    public function testDefinitionForVariables()
    {
        Loop::run(function () {
            // echo $var;
            // Get definition for $var
            $uri = pathToUri(realpath(__DIR__ . '/../../../../fixtures/references.php'));
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($uri),
                new Position(13, 7)
            );
            $this->assertEquals(new Location($uri, new Range(new Position(12, 0), new Position(12, 10))), $result);
        });
    }

    public function testDefinitionForParamTypeHints()
    {
        Loop::run(function () {
            // function whatever(TestClass $param) {
            // Get definition for TestClass
            $reference = $this->getReferenceLocations('TestClass')[5];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->start
            );
            $this->assertEquals($this->getDefinitionLocation('TestClass'), $result);
        });
    }

    public function testDefinitionForReturnTypeHints()
    {
        Loop::run(function () {
            // function whatever(TestClass $param): TestClass {
            // Get definition for TestClass
            $reference = $this->getReferenceLocations('TestClass')[6];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->start
            );
            $this->assertEquals($this->getDefinitionLocation('TestClass'), $result);
        });
    }

    public function testDefinitionForMethodReturnTypeHints()
    {
        Loop::run(function () {
            // public function testMethod($testParameter): TestInterface
            // Get definition for TestInterface
            $reference = $this->getReferenceLocations('TestInterface')[1];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->start
            );
            $this->assertEquals($this->getDefinitionLocation('TestInterface'), $result);
        });
    }

    public function testDefinitionForParams()
    {
        Loop::run(function () {
            // echo $param;
            // Get definition for $param
            $uri = pathToUri(realpath(__DIR__ . '/../../../../fixtures/references.php'));
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($uri),
                new Position(22, 13)
            );
            $this->assertEquals(new Location($uri, new Range(new Position(21, 18), new Position(21, 34))), $result);
        });
    }

    public function testDefinitionForUsedVariables()
    {
        Loop::run(function () {
            // echo $var;
            // Get definition for $var
            $uri = pathToUri(realpath(__DIR__ . '/../../../../fixtures/references.php'));
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($uri),
                new Position(26, 11)
            );
            $this->assertEquals(new Location($uri, new Range(new Position(25, 22), new Position(25, 26))), $result);
        });
    }

    public function testDefinitionForFunctions()
    {
        Loop::run(function () {
            // test_function();
            // Get definition for test_function
            $reference = $this->getReferenceLocations('test_function()')[0];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->start
            );
            $this->assertEquals($this->getDefinitionLocation('test_function()'), $result);
        });
    }

    public function testDefinitionForUseFunctions()
    {
        Loop::run(function () {
            // use function test_function;
            // Get definition for test_function
            $reference = $this->getReferenceLocations('test_function()')[1];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->start
            );
            $this->assertEquals($this->getDefinitionLocation('test_function()'), $result);
        });
    }

    public function testDefinitionForInstanceOf()
    {
        Loop::run(function () {
            // if ($abc instanceof TestInterface) {
            // Get definition for TestInterface
            $reference = $this->getReferenceLocations('TestInterface')[2];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->start
            );
            $this->assertEquals($this->getDefinitionLocation('TestInterface'), $result);
        });
    }

    public function testDefinitionForNestedMethodCall()
    {
        Loop::run(function () {
            // $obj->testProperty->testMethod();
            // Get definition for testMethod
            $reference = $this->getReferenceLocations('TestClass::testMethod()')[1];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->end
            );
            $this->assertEquals($this->getDefinitionLocation('TestClass::testMethod()'), $result);
        });
    }

    public function testDefinitionForPropertyFetchOnArrayDimFetch()
    {
        Loop::run(function () {
            // TestClass::$staticTestProperty[123]->testProperty;
            // Get definition for testProperty
            $reference = $this->getReferenceLocations('TestClass::testProperty')[3];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->end
            );
            $this->assertEquals($this->getDefinitionLocation('TestClass::testProperty'), $result);
        });
    }
}
