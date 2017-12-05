<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{
    Server, LanguageClient, PhpDocumentLoader, DefinitionResolver
};
use LanguageServer\Index\{Index, ProjectIndex, DependenciesIndex};
use LanguageServer\ContentRetriever\FileSystemContentRetriever;
use LanguageServer\Protocol\{
    TextDocumentIdentifier,
    TextEdit,
    Range,
    Position,
    SignatureHelp,
    SignatureInformation,
    ParameterInformation
};
use function LanguageServer\pathToUri;

class SignatureHelpTest extends TestCase
{
    /**
     * @var Server\TextDocument
     */
    private $textDocument;

    /**
     * @var PhpDocumentLoader
     */
    private $loader;

    public function setUp()
    {
        $client = new LanguageClient(new MockProtocolStream, new MockProtocolStream);
        $projectIndex = new ProjectIndex(new Index, new DependenciesIndex);
        $definitionResolver = new DefinitionResolver($projectIndex);
        $contentRetriever = new FileSystemContentRetriever;
        $this->loader = new PhpDocumentLoader($contentRetriever, $projectIndex, $definitionResolver);
        $this->textDocument = new Server\TextDocument($this->loader, $definitionResolver, $client, $projectIndex);
    }

    /**
     * @dataProvider signatureHelpProvider
     */
    public function testSignatureHelp(Position $position, SignatureHelp $expectedSignature)
    {
        $callsUri = pathToUri(__DIR__ . '/../../../fixtures/signature_help/calls.php');
        $this->loader->open($callsUri, file_get_contents($callsUri));
        $signatureHelp = $this->textDocument->signatureHelp(
            new TextDocumentIdentifier($callsUri),
            $position
        )->wait();
        $this->assertEquals($expectedSignature, $signatureHelp);
    }

    public function signatureHelpProvider(): array
    {
        return [
            'member call' => [
                new Position(48, 9),
                $this->createSignatureHelp([
                    'label' => '(\\Foo\\SomethingElse $a, int|null $b = null)',
                    'documentation' => 'Function doc',
                    'parameters' => [
                        [
                            'label' => '\\Foo\\SomethingElse $a',
                            'documentation' => 'A param with a different doc type',
                        ],
                        [
                            'label' => 'int|null $b = null',
                            'documentation' => 'Param with default value',
                        ],
                    ],
                    'activeSignature' => 0,
                    'activeParameter' => 0,
                ]),
            ],
            'member call 2nd param active' => [
                new Position(49, 12),
                $this->createSignatureHelp([
                    'label' => '(\\Foo\\SomethingElse $a, int|null $b = null)',
                    'documentation' => 'Function doc',
                    'parameters' => [
                        [
                            'label' => '\\Foo\\SomethingElse $a',
                            'documentation' => 'A param with a different doc type',
                        ],
                        [
                            'label' => 'int|null $b = null',
                            'documentation' => 'Param with default value',
                        ],
                    ],
                    'activeSignature' => 0,
                    'activeParameter' => 1,
                ]),
            ],
            'member call 2nd param active and closing )' => [
                new Position(50, 11),
                $this->createSignatureHelp([
                    'label' => '(\\Foo\\SomethingElse $a, int|null $b = null)',
                    'documentation' => 'Function doc',
                    'parameters' => [
                        [
                            'label' => '\\Foo\\SomethingElse $a',
                            'documentation' => 'A param with a different doc type',
                        ],
                        [
                            'label' => 'int|null $b = null',
                            'documentation' => 'Param with default value',
                        ],
                    ],
                    'activeSignature' => 0,
                    'activeParameter' => 1,
                ]),
            ],
            'method with no params' => [
                new Position(51, 9),
                $this->createSignatureHelp([
                    'label' => '()',
                    'documentation' => 'Method with no params',
                    'parameters' => [],
                    'activeSignature' => 0,
                    'activeParameter' => 0,
                ]),
            ],
            'constructor' => [
                new Position(47, 14),
                $this->createSignatureHelp([
                    'label' => '(string $first, int $second, \Foo\Test $third)',
                    'documentation' => 'Constructor comment goes here',
                    'parameters' => [
                        [
                            'label' => 'string $first',
                            'documentation' => 'First param',

                        ],
                        [
                            'label' => 'int $second',
                            'documentation' => 'Second param',
                        ],
                        [
                            'label' => '\Foo\Test $third',
                            'documentation' => 'Third param with a longer description',
                        ],
                    ],
                    'activeSignature' => 0,
                    'activeParameter' => 0,
                ]),
            ],
            'global function' => [
                new Position(53, 4),
                $this->createSignatureHelp([
                    'label' => '(int $i, bool $b = false)',
                    'documentation' => null,
                    'parameters' => [
                        [
                            'label' => 'int $i',
                            'documentation' => 'Global function param one',
                        ],
                        [
                            'label' => 'bool $b = false',
                            'documentation' => 'Default false param',
                        ],
                    ],
                    'activeSignature' => 0,
                    'activeParameter' => 0,
                ]),
            ],
            'static method' => [
                new Position(55, 10),
                $this->createSignatureHelp([
                    'label' => '(mixed $a)',
                    'documentation' => null,
                    'parameters' => [
                        [
                            'label' => 'mixed $a',
                            'documentation' => null,
                        ],
                    ],
                    'activeSignature' => 0,
                    'activeParameter' => 0,
                ]),
            ],
        ];
    }

    private function createSignatureHelp(array $info): SignatureHelp
    {
        $params = [];
        foreach ($info['parameters'] as $param) {
            $paramInfo = new ParameterInformation($param['label'], $param['documentation']);
            $params[] = $paramInfo;
        }
        $signature = new SignatureInformation($info['label'], $params, $info['documentation']);
        $help = new SignatureHelp([$signature], $info['activeSignature'], $info['activeParameter']);

        return $help;
    }
}
