<?php
declare(strict_types=1);

namespace LanguageServer\Tests\Server\TextDocument;

use Amp\Loop;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\Tests\Server\ServerTestCase;
use LanguageServer\{Server, LanguageClient, Project};
use LanguageServerProtocol\{TextDocumentIdentifier, Position, Range, Hover, MarkedString};
use function LanguageServer\pathToUri;

class HoverTest extends ServerTestCase
{
    public function testHoverForClassLike()
    {
        Loop::run(function () {
            // $obj = new TestClass();
            // Get hover for TestClass
            $reference = $this->getReferenceLocations('TestClass')[1];
            $result = yield $this->textDocument->hover(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->start
            );
            $this->assertEquals(new Hover([
                new MarkedString('php', "<?php\nclass TestClass implements TestInterface"),
                'Pariatur ut laborum tempor voluptate consequat ea deserunt.' . "\n\n" .
                'Deserunt enim minim sunt sint ea nisi. Deserunt excepteur tempor id nostrud' . "\n" .
                'laboris commodo ad commodo velit mollit qui non officia id. Nulla duis veniam' . "\n" .
                'veniam officia deserunt et non dolore mollit ea quis eiusmod sit non. Occaecat' . "\n" .
                'consequat sunt culpa exercitation pariatur id reprehenderit nisi incididunt Lorem' . "\n" .
                'sint. Officia culpa pariatur laborum nostrud cupidatat consequat mollit.'
            ], $reference->range), $result);
        });
    }

    public function testHoverForClassLikeDefinition()
    {
        Loop::run(function () {
            // class TestClass implements TestInterface
            // Get hover for TestClass
            $definition = $this->getDefinitionLocation('TestClass');
            $result = yield $this->textDocument->hover(
                new TextDocumentIdentifier($definition->uri),
                $definition->range->start
            );
            $this->assertEquals(new Hover([
                new MarkedString('php', "<?php\nclass TestClass implements TestInterface"),
                'Pariatur ut laborum tempor voluptate consequat ea deserunt.' . "\n\n" .
                'Deserunt enim minim sunt sint ea nisi. Deserunt excepteur tempor id nostrud' . "\n" .
                'laboris commodo ad commodo velit mollit qui non officia id. Nulla duis veniam' . "\n" .
                'veniam officia deserunt et non dolore mollit ea quis eiusmod sit non. Occaecat' . "\n" .
                'consequat sunt culpa exercitation pariatur id reprehenderit nisi incididunt Lorem' . "\n" .
                'sint. Officia culpa pariatur laborum nostrud cupidatat consequat mollit.'
            ], $definition->range), $result);
        });
    }

    public function testHoverForMethod()
    {
        Loop::run(function () {
            // $obj->testMethod();
            // Get hover for testMethod
            $reference = $this->getReferenceLocations('TestClass::testMethod()')[0];
            $result = yield $this->textDocument->hover(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->end
            );
            $this->assertEquals(new Hover([
                new MarkedString('php', "<?php\npublic function testMethod(\$testParameter): TestInterface"),
                'Non culpa nostrud mollit esse sunt laboris in irure ullamco cupidatat amet.'
            ], $reference->range), $result);
        });
    }

    public function testHoverForProperty()
    {
        Loop::run(function () {
            // echo $obj->testProperty;
            // Get hover for testProperty
            $reference = $this->getReferenceLocations('TestClass::testProperty')[0];
            $result = yield $this->textDocument->hover(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->end
            );
            $this->assertEquals(new Hover([
                new MarkedString('php', "<?php\npublic \$testProperty;"),
                'Reprehenderit magna velit mollit ipsum do.'
            ], $reference->range), $result);
        });
    }

    public function testHoverForStaticMethod()
    {
        Loop::run(function () {
            // TestClass::staticTestMethod();
            // Get hover for staticTestMethod
            $reference = $this->getReferenceLocations('TestClass::staticTestMethod()')[0];
            $result = yield $this->textDocument->hover(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->end
            );
            $this->assertEquals(new Hover([
                new MarkedString('php', "<?php\npublic static function staticTestMethod()"),
                'Do magna consequat veniam minim proident eiusmod incididunt aute proident.'
            ], $reference->range), $result);
        });
    }

    public function testHoverForStaticProperty()
    {
        Loop::run(function () {
            // echo TestClass::staticTestProperty;
            // Get hover for staticTestProperty
            $reference = $this->getReferenceLocations('TestClass::staticTestProperty')[0];
            $result = yield $this->textDocument->hover(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->end
            );
            $this->assertEquals(new Hover([
                new MarkedString('php', "<?php\npublic static \$staticTestProperty;"),
                'Lorem excepteur officia sit anim velit veniam enim.'
            ], $reference->range), $result);
        });
    }

    public function testHoverForClassConstant()
    {
        Loop::run(function () {
            // echo TestClass::TEST_CLASS_CONST;
            // Get hover for TEST_CLASS_CONST
            $reference = $this->getReferenceLocations('TestClass::TEST_CLASS_CONST')[0];
            $result = yield $this->textDocument->hover(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->end
            );
            $this->assertEquals(new Hover([
                new MarkedString('php', "<?php\nconst TEST_CLASS_CONST = 123;"),
                'Anim labore veniam consectetur laboris minim quis aute aute esse nulla ad.'
            ], $reference->range), $result);
        });
    }

    public function testHoverForFunction()
    {
        Loop::run(function () {
            // test_function();
            // Get hover for test_function
            $reference = $this->getReferenceLocations('test_function()')[0];
            $result = yield $this->textDocument->hover(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->end
            );
            $this->assertEquals(new Hover([
                new MarkedString('php', "<?php\nfunction test_function()"),
                'Officia aliquip adipisicing et nulla et laboris dolore labore.'
            ], $reference->range), $result);
        });
    }

    public function testHoverForConstant()
    {
        Loop::run(function () {
            // echo TEST_CONST;
            // Get hover for TEST_CONST
            $reference = $this->getReferenceLocations('TEST_CONST')[0];
            $result = yield $this->textDocument->hover(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->end
            );
            $this->assertEquals(new Hover([
                new MarkedString('php', "<?php\nconst TEST_CONST = 123;"),
                'Esse commodo excepteur pariatur Lorem est aute incididunt reprehenderit.'
            ], $reference->range), $result);
        });
    }

    public function testHoverForGlobalConstant()
    {
        Loop::run(function () {
            // print TEST_DEFINE_CONSTANT ? 'true' : 'false';
            // Get hover for TEST_DEFINE_CONSTANT
            $reference = $this->getReferenceLocations('TEST_DEFINE_CONSTANT')[0];
            $result = yield $this->textDocument->hover(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->end
            );
            // TODO - should pretty print with fqns, like \define, \false. Not yet supported by tolerant-php-parser
            $this->assertEquals(new Hover([
                new MarkedString('php', "<?php\ndefine('TEST_DEFINE_CONSTANT', false)"),
                'Lorem ipsum dolor sit amet, consectetur.'
            ], $reference->range), $result);
        });
    }

    public function testHoverForVariable()
    {
        Loop::run(function () {
            // echo $var;
            // Get hover for $var
            $uri = pathToUri(realpath(__DIR__ . '/../../../fixtures/references.php'));
            $result = yield $this->textDocument->hover(
                new TextDocumentIdentifier($uri),
                new Position(13, 7)
            );
            $this->assertEquals(new Hover(
                [new MarkedString('php', "<?php\n\$var = 123")],
                new Range(new Position(13, 5), new Position(13, 9))
            ), $result);
        });
    }

    public function testHoverForParam()
    {
        Loop::run(function () {
            // echo $param;
            // Get hover for $param
            $uri = pathToUri(realpath(__DIR__ . '/../../../fixtures/references.php'));
            $result = yield $this->textDocument->hover(
                new TextDocumentIdentifier($uri),
                new Position(22, 11)
            );
            $this->assertEquals(new Hover(
                [
                    new MarkedString('php', "<?php\nTestClass \$param"),
                    'Adipisicing non non cillum sint incididunt cillum enim mollit.'
                ],
                new Range(new Position(22, 9), new Position(22, 15))
            ), $result);
        });
    }

    public function testHoverForThis()
    {
        Loop::run(function () {
            // $this;
            // Get hover for $this
            $uri = pathToUri(realpath(__DIR__ . '/../../../fixtures/global_symbols.php'));
            $result = yield $this->textDocument->hover(
                new TextDocumentIdentifier($uri),
                new Position(59, 11)
            );
            $this->assertEquals(new Hover([
                new MarkedString('php', "<?php\nclass TestClass implements TestInterface"),
                'Pariatur ut laborum tempor voluptate consequat ea deserunt.' . "\n\n" .
                'Deserunt enim minim sunt sint ea nisi. Deserunt excepteur tempor id nostrud' . "\n" .
                'laboris commodo ad commodo velit mollit qui non officia id. Nulla duis veniam' . "\n" .
                'veniam officia deserunt et non dolore mollit ea quis eiusmod sit non. Occaecat' . "\n" .
                'consequat sunt culpa exercitation pariatur id reprehenderit nisi incididunt Lorem' . "\n" .
                'sint. Officia culpa pariatur laborum nostrud cupidatat consequat mollit.'
            ], new Range(new Position(59, 8), new Position(59, 13))), $result);
        });
    }
}
