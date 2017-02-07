<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server;

use PHPUnit\Framework\TestCase;
use phpDocumentor\Reflection\DocBlockFactory;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{LanguageClient, PhpDocument, DefinitionResolver, Parser};
use LanguageServer\NodeVisitor\NodeAtPositionFinder;
use LanguageServer\ContentRetriever\FileSystemContentRetriever;
use LanguageServer\Protocol\{SymbolKind, Position, ClientCapabilities};
use LanguageServer\Index\{Index, ProjectIndex, DependenciesIndex};
use PhpParser\Node;
use function LanguageServer\isVendored;

class PhpDocumentTest extends TestCase
{
    public function createDocument(string $uri, string $content)
    {
        $parser = new Parser;
        $docBlockFactory = DocBlockFactory::createInstance();
        $index = new Index;
        $definitionResolver = new DefinitionResolver($index);
        return new PhpDocument($uri, $content, $index, $parser, $docBlockFactory, $definitionResolver);
    }

    public function testParsesVariableVariables()
    {
        $document = $this->createDocument('whatever', "<?php\n$\$a = 'foo';\n\$bar = 'baz';\n");

        $this->assertEquals([], $document->getDefinitions());
    }

    public function testGetNodeAtPosition()
    {
        $document = $this->createDocument('whatever', "<?php\n$\$a = new SomeClass;");
        $node = $document->getNodeAtPosition(new Position(1, 13));
        $this->assertInstanceOf(Node\Name\FullyQualified::class, $node);
        $this->assertEquals('SomeClass', (string)$node);
    }

    public function testIsVendored()
    {
        $document = $this->createDocument('file:///dir/vendor/x.php', "<?php\n$\$a = new SomeClass;");
        $this->assertEquals(true, isVendored($document));

        $document = $this->createDocument('file:///c:/dir/vendor/x.php', "<?php\n$\$a = new SomeClass;");
        $this->assertEquals(true, isVendored($document));

        $document = $this->createDocument('file:///vendor/x.php', "<?php\n$\$a = new SomeClass;");
        $this->assertEquals(true, isVendored($document));

        $document = $this->createDocument('file:///dir/vendor.php', "<?php\n$\$a = new SomeClass;");
        $this->assertEquals(false, isVendored($document));

        $document = $this->createDocument('file:///dir/x.php', "<?php\n$\$a = new SomeClass;");
        $this->assertEquals(false, isVendored($document));
    }
}
