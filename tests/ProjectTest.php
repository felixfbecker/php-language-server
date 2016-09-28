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

    public function testFindSymbols()
    {
        $this->project->getDocument('file:///document1.php')->updateContent("<?php\nfunction foo() {}\nfunction bar() {}\n");
        $this->project->getDocument('file:///document2.php')->updateContent("<?php\nfunction baz() {}\nfunction frob() {}\n");

        $symbols = $this->project->findSymbols('ba');        

        $this->assertEquals([
            [
                'name' => 'bar',
                'kind' => SymbolKind::FUNCTION,
                'location' => [
                    'uri' => 'file:///document1.php',
                    'range' => [
                        'start' => [
                            'line' => 2,
                            'character' => 0
                        ],
                        'end' => [
                            'line' => 2,
                            'character' => 17
                        ]
                    ]
                ],
                'containerName' => null
            ],
            [
                'name' => 'baz',
                'kind' => SymbolKind::FUNCTION,
                'location' => [
                    'uri' => 'file:///document2.php',
                    'range' => [
                        'start' => [
                            'line' => 1,
                            'character' => 0
                        ],
                        'end' => [
                            'line' => 1,
                            'character' => 17
                        ]
                    ]
                ],
                'containerName' => null
            ]
        ], json_decode(json_encode($symbols), true));
    }
}
