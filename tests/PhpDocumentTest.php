<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{LanguageClient, Project};
use LanguageServer\Protocol\SymbolKind;

class PhpDocumentTest extends TestCase
{
     /**
     * @var Project $project
     */
    private $project;

    public function setUp()
    {
        $this->project = new Project(new LanguageClient(new MockProtocolStream()));
    }

    public function testParsesVariableVariables()
    {
        $document = $this->project->getDocument('whatever');
        
        $document->updateContent("<?php\n$\$a = 'foo';\n\$bar = 'baz';\n");

        $symbols = $document->getSymbols();

        $this->assertEquals([
            [
                'name' => 'a',
                'kind' => SymbolKind::VARIABLE,
                'location' => [
                    'uri' => 'whatever',
                    'range' => [
                        'start' => [
                            'line' => 1,
                            'character' => 0
                        ],
                        'end' => [
                            'line' => 1,
                            'character' => 3
                        ]
                    ]
                ],
                'containerName' => null
            ],
            [
                'name' => 'bar',
                'kind' => SymbolKind::VARIABLE,
                'location' => [
                    'uri' => 'whatever',
                    'range' => [
                        'start' => [
                            'line' => 2,
                            'character' => 0
                        ],
                        'end' => [
                            'line' => 2,
                            'character' => 4
                        ]
                    ]
                ],
                'containerName' => null
            ]
        ], json_decode(json_encode($symbols), true));
    }
}
