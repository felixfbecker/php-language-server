<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{LanguageClient, Project};
use LanguageServer\NodeVisitor\NodeAtPositionFinder;
use LanguageServer\Protocol\{SymbolKind, Position};
use PhpParser\Node;

class PhpDocumentTest extends TestCase
{
     /**
     * @var Project $project
     */
    private $project;

    public function setUp()
    {
        $this->project = new Project(new LanguageClient(new MockProtocolStream, new MockProtocolStream));
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
}
