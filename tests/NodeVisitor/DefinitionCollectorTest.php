<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use PHPUnit\Framework\TestCase;
use phpDocumentor\Reflection\DocBlockFactory;
use LanguageServer\{
    DefinitionResolver, TreeAnalyzer
};
use LanguageServer\Index\{Index};
use function LanguageServer\pathToUri;
use Microsoft\PhpParser as Tolerant;

class DefinitionCollectorTest extends TestCase
{
    public function testCollectsSymbols()
    {
        $path = realpath(__DIR__ . '/../../fixtures/symbols.php');
        $defNodes = $this->collectDefinitions($path);

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
            'TestNamespace\\ChildClass'
        ], array_keys($defNodes));


        $this->assertInstanceOf(Tolerant\Node\ConstElement::class, $defNodes['TestNamespace\\TEST_CONST']);
        $this->assertInstanceOf(Tolerant\Node\Statement\ClassDeclaration::class, $defNodes['TestNamespace\\TestClass']);
        $this->assertInstanceOf(Tolerant\Node\ConstElement::class, $defNodes['TestNamespace\\TestClass::TEST_CLASS_CONST']);
        // TODO - should we parse properties more strictly?
        $this->assertInstanceOf(Tolerant\Node\Expression\Variable::class, $defNodes['TestNamespace\\TestClass::$staticTestProperty']);
        $this->assertInstanceOf(Tolerant\Node\Expression\Variable::class, $defNodes['TestNamespace\\TestClass->testProperty']);
        $this->assertInstanceOf(Tolerant\Node\MethodDeclaration::class, $defNodes['TestNamespace\\TestClass::staticTestMethod()']);
        $this->assertInstanceOf(Tolerant\Node\MethodDeclaration::class, $defNodes['TestNamespace\\TestClass->testMethod()']);
        $this->assertInstanceOf(Tolerant\Node\Statement\TraitDeclaration::class, $defNodes['TestNamespace\\TestTrait']);
        $this->assertInstanceOf(Tolerant\Node\Statement\InterfaceDeclaration::class, $defNodes['TestNamespace\\TestInterface']);
        $this->assertInstanceOf(Tolerant\Node\Statement\FunctionDeclaration::class, $defNodes['TestNamespace\\test_function()']);
        $this->assertInstanceOf(Tolerant\Node\Statement\ClassDeclaration::class, $defNodes['TestNamespace\\ChildClass']);
    }

    public function testDoesNotCollectReferences()
    {
        $path = realpath(__DIR__ . '/../../fixtures/references.php');
        $defNodes = $this->collectDefinitions($path);

        $this->assertEquals(['TestNamespace', 'TestNamespace\\whatever()'], array_keys($defNodes));
        $this->assertInstanceOf(Tolerant\Node\Statement\NamespaceDefinition::class, $defNodes['TestNamespace']);
        $this->assertInstanceOf(Tolerant\Node\Statement\FunctionDeclaration::class, $defNodes['TestNamespace\\whatever()']);
    }

    /**
     * @param $path
     */
    private function collectDefinitions($path): array
    {
        $uri = pathToUri($path);
        $parser = new Tolerant\Parser();

        $docBlockFactory = DocBlockFactory::createInstance();
        $index = new Index;
        $definitionResolver = new DefinitionResolver($index);
        $content = file_get_contents($path);

        $treeAnalyzer = new TreeAnalyzer($parser, $content, $docBlockFactory, $definitionResolver, $uri);
        return $treeAnalyzer->getDefinitionNodes();
    }
}
