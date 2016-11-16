<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use PHPUnit\Framework\TestCase;
use PhpParser\{NodeTraverser, Node};
use PhpParser\NodeVisitor\NameResolver;
use LanguageServer\{LanguageClient, Project, PhpDocument, Parser};
use LanguageServer\Protocol\ClientCapabilities;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\NodeVisitor\{ReferencesAdder, DefinitionCollector};
use function LanguageServer\pathToUri;

class DefinitionCollectorTest extends TestCase
{
    public function testCollectsSymbols()
    {
        $client = new LanguageClient(new MockProtocolStream, new MockProtocolStream);
        $project = new Project($client, new ClientCapabilities);
        $parser = new Parser;
        $uri = pathToUri(realpath(__DIR__ . '/../../fixtures/symbols.php'));
        $document = $project->loadDocument($uri)->wait();
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);
        $traverser->addVisitor(new ReferencesAdder($document));
        $definitionCollector = new DefinitionCollector;
        $traverser->addVisitor($definitionCollector);
        $stmts = $parser->parse(file_get_contents($uri));
        $traverser->traverse($stmts);
        $defNodes = $definitionCollector->nodes;
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
        ], array_keys($defNodes));
        $this->assertInstanceOf(Node\Const_::class, $defNodes['TestNamespace\\TEST_CONST']);
        $this->assertInstanceOf(Node\Stmt\Class_::class, $defNodes['TestNamespace\\TestClass']);
        $this->assertInstanceOf(Node\Const_::class, $defNodes['TestNamespace\\TestClass::TEST_CLASS_CONST']);
        $this->assertInstanceOf(Node\Stmt\PropertyProperty::class, $defNodes['TestNamespace\\TestClass::staticTestProperty']);
        $this->assertInstanceOf(Node\Stmt\PropertyProperty::class, $defNodes['TestNamespace\\TestClass::testProperty']);
        $this->assertInstanceOf(Node\Stmt\ClassMethod::class, $defNodes['TestNamespace\\TestClass::staticTestMethod()']);
        $this->assertInstanceOf(Node\Stmt\ClassMethod::class, $defNodes['TestNamespace\\TestClass::testMethod()']);
        $this->assertInstanceOf(Node\Stmt\Trait_::class, $defNodes['TestNamespace\\TestTrait']);
        $this->assertInstanceOf(Node\Stmt\Interface_::class, $defNodes['TestNamespace\\TestInterface']);
        $this->assertInstanceOf(Node\Stmt\Function_::class, $defNodes['TestNamespace\\test_function()']);
    }

    public function testDoesNotCollectReferences()
    {
        $client = new LanguageClient(new MockProtocolStream, new MockProtocolStream);
        $project = new Project($client, new ClientCapabilities);
        $parser = new Parser;
        $uri = pathToUri(realpath(__DIR__ . '/../../fixtures/references.php'));
        $document = $project->loadDocument($uri)->wait();
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);
        $traverser->addVisitor(new ReferencesAdder($document));
        $definitionCollector = new DefinitionCollector;
        $traverser->addVisitor($definitionCollector);
        $stmts = $parser->parse(file_get_contents($uri));
        $traverser->traverse($stmts);
        $defNodes = $definitionCollector->nodes;
        $this->assertEquals(['TestNamespace\\whatever()'], array_keys($defNodes));
        $this->assertInstanceOf(Node\Stmt\Function_::class, $defNodes['TestNamespace\\whatever()']);
    }
}
