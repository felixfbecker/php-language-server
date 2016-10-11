<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use PHPUnit\Framework\TestCase;
use PhpParser\{ParserFactory, NodeTraverser, Node};
use PhpParser\NodeVisitor\NameResolver;
use LanguageServer\{LanguageClient, Project, PhpDocument};
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\NodeVisitor\{ReferencesAdder, DefinitionCollector};
use function LanguageServer\pathToUri;

class DefinitionCollectorTest extends TestCase
{
    public function testCollectsSymbols()
    {
        $client = new LanguageClient(new MockProtocolStream());
        $project = new Project($client);
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $uri = pathToUri(realpath(__DIR__ . '/../../fixtures/symbols.php'));
        $document = $project->loadDocument($uri);
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);
        $traverser->addVisitor(new ReferencesAdder($document));
        $definitionCollector = new DefinitionCollector;
        $traverser->addVisitor($definitionCollector);
        $stmts = $parser->parse(file_get_contents($uri));
        $traverser->traverse($stmts);
        $defs = $definitionCollector->definitions;
        $this->assertEquals([
            'TestNamespace\\TEST_CONST',
            'TestNamespace\\TestClass',
            'TestNamespace\\TestClass::TEST_CLASS_CONST',
            'TestNamespace\\TestClass::staticTestProperty',
            'TestNamespace\\TestClass::testProperty',
            'TestNamespace\\TestClass::staticTestMethod()',
            'TestNamespace\\TestClass::testMethod()',
            'TestNamespace\\TestTrait',
            'TestNamespace\\TestInterface',
            'TestNamespace\\test_function()'
        ], array_keys($defs));
        $this->assertInstanceOf(Node\Const_::class, $defs['TestNamespace\\TEST_CONST']);
        $this->assertInstanceOf(Node\Stmt\Class_::class, $defs['TestNamespace\\TestClass']);
        $this->assertInstanceOf(Node\Const_::class, $defs['TestNamespace\\TestClass::TEST_CLASS_CONST']);
        $this->assertInstanceOf(Node\Stmt\PropertyProperty::class, $defs['TestNamespace\\TestClass::staticTestProperty']);
        $this->assertInstanceOf(Node\Stmt\PropertyProperty::class, $defs['TestNamespace\\TestClass::testProperty']);
        $this->assertInstanceOf(Node\Stmt\ClassMethod::class, $defs['TestNamespace\\TestClass::staticTestMethod()']);
        $this->assertInstanceOf(Node\Stmt\ClassMethod::class, $defs['TestNamespace\\TestClass::testMethod()']);
        $this->assertInstanceOf(Node\Stmt\Trait_::class, $defs['TestNamespace\\TestTrait']);
        $this->assertInstanceOf(Node\Stmt\Interface_::class, $defs['TestNamespace\\TestInterface']);
        $this->assertInstanceOf(Node\Stmt\Function_::class, $defs['TestNamespace\\test_function()']);
    }

    public function testDoesNotCollectReferences()
    {
        $client = new LanguageClient(new MockProtocolStream());
        $project = new Project($client);
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $uri = pathToUri(realpath(__DIR__ . '/../../fixtures/references.php'));
        $document = $project->loadDocument($uri);
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);
        $traverser->addVisitor(new ReferencesAdder($document));
        $definitionCollector = new DefinitionCollector;
        $traverser->addVisitor($definitionCollector);
        $stmts = $parser->parse(file_get_contents($uri));
        $traverser->traverse($stmts);
        $defs = $definitionCollector->definitions;
        $this->assertEquals(['TestNamespace\\whatever()'], array_keys($defs));
        $this->assertInstanceOf(Node\Stmt\Function_::class, $defs['TestNamespace\\whatever()']);
    }
}
