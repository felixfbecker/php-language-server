<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, Client, LanguageClient, Project, PhpDocument};
use LanguageServer\Protocol\{TextDocumentItem, TextDocumentIdentifier, SymbolKind, DiagnosticSeverity, FormattingOptions};
use AdvancedJsonRpc\{Request as RequestBody, Response as ResponseBody};

class ProjectTest extends TestCase
{
    /**
     * @var Project $project
     */
    private $project;

    public function setUp()
    {
        $this->project = new Project(new LanguageClient(new MockProtocolStream()));
    }

    public function testGetDocumentCreatesNewDocument()
    {
        $document = $this->project->getDocument('file:///document1.php');

        $this->assertNotNull($document);
        $this->assertInstanceOf(PhpDocument::class, $document);
    }

    public function testGetDocumentCreatesDocumentOnce()
    {
        $document1 = $this->project->getDocument('file:///document1.php');
        $document2 = $this->project->getDocument('file:///document1.php');

        $this->assertSame($document1, $document2);
    }
}
