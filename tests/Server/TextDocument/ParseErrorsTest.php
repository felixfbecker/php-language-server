<?php
declare(strict_types=1);

namespace LanguageServer\Tests\Server\TextDocument;

use Amp\Deferred;
use Amp\Delayed;
use Amp\Loop;
use JsonMapper;
use LanguageServer\{Client, ClientHandler, DefinitionResolver, LanguageClient, PhpDocumentLoader, Server};
use LanguageServer\ContentRetriever\FileSystemContentRetriever;
use LanguageServer\Index\{DependenciesIndex, Index, ProjectIndex};
use LanguageServer\Tests\MockProtocolStream;
use LanguageServerProtocol\{DiagnosticSeverity, TextDocumentItem};
use PHPUnit\Framework\TestCase;

class ParseErrorsTest extends TestCase
{
    /**
     * @var Server\TextDocument
     */
    private $textDocument;

    /**
     * @var Deferred
     */
    private $args;

    public function setUp()
    {
        $client = new LanguageClient(new MockProtocolStream, new MockProtocolStream);
        $this->args = new Deferred();
        $client->textDocument = new class($this->args) extends Client\TextDocument
        {
            /** @var Deferred */
            private $args;

            public function __construct($args)
            {
                parent::__construct(new ClientHandler(new MockProtocolStream, new MockProtocolStream), new JsonMapper);
                $this->args = $args;
            }

            public function publishDiagnostics(string $uri, array $diagnostics): \Generator
            {
                $this->args->resolve(func_get_args());
                yield new Delayed(0);
                return null;
            }
        };
        $projectIndex = new ProjectIndex(new Index, new DependenciesIndex);
        $definitionResolver = new DefinitionResolver($projectIndex);
        $loader = new PhpDocumentLoader(new FileSystemContentRetriever, $projectIndex, $definitionResolver);
        $this->textDocument = new Server\TextDocument($loader, $definitionResolver, $client, $projectIndex);
    }

    private function openFile($file)
    {
        $textDocumentItem = new TextDocumentItem();
        $textDocumentItem->uri = 'whatever';
        $textDocumentItem->languageId = 'php';
        $textDocumentItem->version = 1;
        $textDocumentItem->text = file_get_contents($file);
        $this->textDocument->didOpen($textDocumentItem);
    }

    public function testParseErrorsArePublishedAsDiagnostics()
    {
        Loop::run(function () {
            $this->openFile(__DIR__ . '/../../../fixtures/invalid_file.php');
            $this->assertEquals([
                'whatever',
                [[
                    'range' => [
                        'start' => [
                            'line' => 2,
                            'character' => 9
                        ],
                        'end' => [
                            'line' => 2,
                            'character' => 9
                        ]
                    ],
                    'severity' => DiagnosticSeverity::ERROR,
                    'code' => null,
                    'source' => 'php',
                    'message' => "'Name' expected."
                ],
                    [
                        'range' => [
                            'start' => [
                                'line' => 2,
                                'character' => 9
                            ],
                            'end' => [
                                'line' => 2,
                                'character' => 9
                            ]
                        ],
                        'severity' => DiagnosticSeverity::ERROR,
                        'code' => null,
                        'source' => 'php',
                        'message' => "'{' expected."
                    ],
                    [
                        'range' => [
                            'start' => [
                                'line' => 2,
                                'character' => 9
                            ],
                            'end' => [
                                'line' => 2,
                                'character' => 9
                            ]
                        ],
                        'severity' => DiagnosticSeverity::ERROR,
                        'code' => null,
                        'source' => 'php',
                        'message' => "'}' expected."
                    ],
                    [
                        'range' => [
                            'start' => [
                                'line' => 2,
                                'character' => 15
                            ],
                            'end' => [
                                'line' => 2,
                                'character' => 15
                            ]
                        ],
                        'severity' => DiagnosticSeverity::ERROR,
                        'code' => null,
                        'source' => 'php',
                        'message' => "'Name' expected."
                    ]]
            ], json_decode(json_encode(yield $this->args->promise()), true));
        });
    }

    public function testParseErrorsWithOnlyStartLine()
    {
        Loop::run(function () {
            $this->markTestIncomplete('This diagnostic not yet implemented in tolerant-php-parser');
            yield $this->openFile(__DIR__ . '/../../../fixtures/namespace_not_first.php');
            $this->assertEquals([
                'whatever',
                [[
                    'range' => [
                        'start' => [
                            'line' => 4,
                            'character' => 0
                        ],
                        'end' => [
                            'line' => 4,
                            'character' => 0
                        ]
                    ],
                    'severity' => DiagnosticSeverity::ERROR,
                    'code' => null,
                    'source' => 'php',
                    'message' => "Namespace declaration statement has to be the very first statement in the script"
                ]]
            ], json_decode(json_encode(yield $this->args->promise()), true));
        });
    }
}
