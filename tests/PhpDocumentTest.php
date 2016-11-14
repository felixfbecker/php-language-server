<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{LanguageClient, Project};
use LanguageServer\NodeVisitor\NodeAtPositionFinder;
use LanguageServer\Protocol\{SymbolKind, Position, ClientCapabilities};
use PhpParser\Node;

class PhpDocumentTest extends TestCase
{
     /**
     * @var Project $project
     */
    private $project;

    public function setUp()
    {
        $client = new LanguageClient(new MockProtocolStream, new MockProtocolStream);
        $this->project = new Project($client, new ClientCapabilities);
    }

    public function testParsesVariableVariables()
    {
        $document = $this->project->openDocument('whatever', "<?php\n$\$a = 'foo';\n\$bar = 'baz';\n");

        $this->assertEquals([], $document->getDefinitions());
    }

    public function testGetNodeAtPosition()
    {
        $document = $this->project->openDocument('whatever', "<?php\n$\$a = new SomeClass;");
        $node = $document->getNodeAtPosition(new Position(1, 13));
        $this->assertInstanceOf(Node\Name\FullyQualified::class, $node);
        $this->assertEquals('SomeClass', (string)$node);
    }

    public function testIsVendored()
    {
        $document = $this->project->openDocument('file:///dir/vendor/x.php', "<?php\n$\$a = new SomeClass;");
        $this->assertEquals(true, $document->isVendored());

        $document = $this->project->openDocument('file:///c:/dir/vendor/x.php', "<?php\n$\$a = new SomeClass;");
        $this->assertEquals(true, $document->isVendored());

        $document = $this->project->openDocument('file:///vendor/x.php', "<?php\n$\$a = new SomeClass;");
        $this->assertEquals(true, $document->isVendored());

        $document = $this->project->openDocument('file:///dir/vendor.php', "<?php\n$\$a = new SomeClass;");
        $this->assertEquals(false, $document->isVendored());

        $document = $this->project->openDocument('file:///dir/x.php', "<?php\n$\$a = new SomeClass;");
        $this->assertEquals(false, $document->isVendored());
    }
}
