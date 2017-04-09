<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use PHPUnit\Framework\TestCase;
use PhpParser\{NodeTraverser, Node};
use PhpParser\NodeVisitor\NameResolver;
use phpDocumentor\Reflection\DocBlockFactory;
use LanguageServer\{LanguageClient, PhpDocument, PhpDocumentLoader, Parser, DefinitionResolver};
use LanguageServer\ContentRetriever\FileSystemContentRetriever;
use LanguageServer\Protocol\ClientCapabilities;
use LanguageServer\Index\{ProjectIndex, Index, DependenciesIndex};
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\NodeVisitor\{ReferencesAdder, DefinitionCollector};
use function LanguageServer\pathToUri;

class DefinitionCollectorTest extends TestCase
{
    public function testCollectsSymbols()
    {
        $path = realpath(__DIR__ . '/../../fixtures/symbols.php');
        $uri = pathToUri($path);
        $parser = new Parser;
        $docBlockFactory = DocBlockFactory::createInstance();
        $index = new Index;
        $definitionResolver = new DefinitionResolver($index);
        $content = file_get_contents($path);
        $document = new PhpDocument($uri, $content, $index, $parser, $docBlockFactory, $definitionResolver);
        $stmts = $parser->parse($content);

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);
        $traverser->addVisitor(new ReferencesAdder($document));
        $definitionCollector = new DefinitionCollector($definitionResolver);
        $traverser->addVisitor($definitionCollector);
        $traverser->traverse($stmts);

        $defNodes = $definitionCollector->nodes;

        $this->assertEquals([
            'TestNamespace',
            'TestNamespace\\TEST_CONST',
            'TestNamespace\\TestClass',
            'TestNamespace\\TestClass::TEST_CLASS_CONST',
            'TestNamespace\\TestClass::$staticTestProperty',
            'TestNamespace\\TestClass->testProperty',
            'TestNamespace\\TestClass::staticTestMethod()',
            'TestNamespace\\TestClass->testMethod()',
            'TestNamespace\\TestTrait',
            'TestNamespace\\TestInterface',
            'TestNamespace\\test_function()',
            'TestNamespace\\ChildClass',
            'TestNamespace\\Example',
            'TestNamespace\\Example->__construct()',
            'TestNamespace\\Example->__destruct()'
        ], array_keys($defNodes));
        $this->assertInstanceOf(Node\Const_::class, $defNodes['TestNamespace\\TEST_CONST']);
        $this->assertInstanceOf(Node\Stmt\Class_::class, $defNodes['TestNamespace\\TestClass']);
        $this->assertInstanceOf(Node\Const_::class, $defNodes['TestNamespace\\TestClass::TEST_CLASS_CONST']);
        $this->assertInstanceOf(Node\Stmt\PropertyProperty::class, $defNodes['TestNamespace\\TestClass::$staticTestProperty']);
        $this->assertInstanceOf(Node\Stmt\PropertyProperty::class, $defNodes['TestNamespace\\TestClass->testProperty']);
        $this->assertInstanceOf(Node\Stmt\ClassMethod::class, $defNodes['TestNamespace\\TestClass::staticTestMethod()']);
        $this->assertInstanceOf(Node\Stmt\ClassMethod::class, $defNodes['TestNamespace\\TestClass->testMethod()']);
        $this->assertInstanceOf(Node\Stmt\Trait_::class, $defNodes['TestNamespace\\TestTrait']);
        $this->assertInstanceOf(Node\Stmt\Interface_::class, $defNodes['TestNamespace\\TestInterface']);
        $this->assertInstanceOf(Node\Stmt\Function_::class, $defNodes['TestNamespace\\test_function()']);
        $this->assertInstanceOf(Node\Stmt\Class_::class, $defNodes['TestNamespace\\ChildClass']);
        $this->assertInstanceOf(Node\Stmt\Class_::class, $defNodes['TestNamespace\\Example']);
        $this->assertInstanceOf(Node\Stmt\ClassMethod::class, $defNodes['TestNamespace\\Example->__construct()']);
        $this->assertInstanceOf(Node\Stmt\ClassMethod::class, $defNodes['TestNamespace\\Example->__destruct()']);
    }

    public function testDoesNotCollectReferences()
    {
        $path = realpath(__DIR__ . '/../../fixtures/references.php');
        $uri = pathToUri($path);
        $parser = new Parser;
        $docBlockFactory = DocBlockFactory::createInstance();
        $index = new Index;
        $definitionResolver = new DefinitionResolver($index);
        $content = file_get_contents($path);
        $document = new PhpDocument($uri, $content, $index, $parser, $docBlockFactory, $definitionResolver);
        $stmts = $parser->parse($content);

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);
        $traverser->addVisitor(new ReferencesAdder($document));
        $definitionCollector = new DefinitionCollector($definitionResolver);
        $traverser->addVisitor($definitionCollector);
        $traverser->traverse($stmts);

        $defNodes = $definitionCollector->nodes;

        $this->assertEquals(['TestNamespace', 'TestNamespace\\whatever()'], array_keys($defNodes));
        $this->assertInstanceOf(Node\Name::class, $defNodes['TestNamespace']);
        $this->assertInstanceOf(Node\Stmt\Namespace_::class, $defNodes['TestNamespace']->getAttribute('parentNode'));
        $this->assertInstanceOf(Node\Stmt\Function_::class, $defNodes['TestNamespace\\whatever()']);
    }
}
