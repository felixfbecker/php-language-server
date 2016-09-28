<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, Client, LanguageClient, Project, PhpDocument};
use LanguageServer\Protocol\{TextDocumentItem, TextDocumentIdentifier, SymbolKind, DiagnosticSeverity, FormattingOptions};
use AdvancedJsonRpc\{Request as RequestBody, Response as ResponseBody};

class WorkspaceTest extends TestCase
{
    /**
     * @var LanguageServer\Workspace $workspace
     */
    private $workspace;

    public function setUp()
    {
        $client = new LanguageClient(new MockProtocolStream());
        $project = new Project($client);
        $this->workspace = new Server\Workspace($project, $client);

        // create two documents
        $project->getDocument('file:///document1.php')->updateContent("<?php\nfunction foo() {}\nfunction bar() {}\n");
        $project->getDocument('file:///document2.php')->updateContent("<?php\nfunction baz() {}\nfunction frob() {}\n");        
    }

    public function testSymbol()
    {
        // Request symbols
        $result = $this->workspace->symbol('f');
        $this->assertEquals([
            [
                'name' => 'foo',
                'kind' => SymbolKind::FUNCTION,
                'location' => [
                    'uri' => 'file:///document1.php',
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
            ],
            [
                'name' => 'frob',
                'kind' => SymbolKind::FUNCTION,
                'location' => [
                    'uri' => 'file:///document2.php',
                    'range' => [
                        'start' => [
                            'line' => 2,
                            'character' => 0
                        ],
                        'end' => [
                            'line' => 2,
                            'character' => 18
                        ]
                    ]
                ],
                'containerName' => null
            ]
        ], json_decode(json_encode($result), true));
    }
}
