<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, LanguageClient, PhpDocumentLoader, CompletionProvider, DefinitionResolver};
use LanguageServer\Index\{Index, ProjectIndex, DependenciesIndex, GlobalIndex, StubsIndex};
use LanguageServer\ContentRetriever\FileSystemContentRetriever;
use LanguageServer\Protocol\{
    TextDocumentIdentifier,
    TextEdit,
    Range,
    Position,
    ClientCapabilities,
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
        $this->loader->load(pathToUri(__DIR__ . '/../../../fixtures/global_symbols.php'))->wait();
        $this->loader->load(pathToUri(__DIR__ . '/../../../fixtures/symbols.php'))->wait();
        $this->textDocument = new Server\TextDocument($this->loader, $definitionResolver, $client, $projectIndex);
    }

    public function testMethodClosed()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/signature/methodClosed.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $result = $this->textDocument->signatureHelp(
            new TextDocumentIdentifier($completionUri),
            new Position(9, 22)
        )->wait();

        $this->assertEquals(new SignatureHelp(
            [
                new SignatureInformation(
                    'method(string $param = "")',
                    null,
                    [
                        new ParameterInformation('string $param = ""')
                    ]
                )
            ]
        ), $result);
    }

    public function testMethodClosedReference()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/signature/methodClosed.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $result = $this->textDocument->signatureHelp(
            new TextDocumentIdentifier($completionUri),
            new Position(14, 11)
        )->wait();

        $this->assertEquals(new SignatureHelp(
            [
                new SignatureInformation(
                    'method(string $param = "")',
                    null,
                    [
                        new ParameterInformation('string $param = ""')
                    ]
                )
            ]
        ), $result);
    }

    public function testMethodNotClosed()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/signature/methodNotClosed.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $result = $this->textDocument->signatureHelp(
            new TextDocumentIdentifier($completionUri),
            new Position(9, 22)
        )->wait();

        $this->assertEquals(new SignatureHelp(
            [
                new SignatureInformation(
                    'method(string $param = "")',
                    null,
                    [
                        new ParameterInformation('string $param = ""')
                    ]
                )
            ]
        ), $result);
    }

    public function testMethodNotClosedReference()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/signature/methodNotClosed.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $result = $this->textDocument->signatureHelp(
            new TextDocumentIdentifier($completionUri),
            new Position(14, 14)
        )->wait();

        $this->assertEquals(new SignatureHelp(
            [
                new SignatureInformation(
                    'method(string $param = "")',
                    null,
                    [
                        new ParameterInformation('string $param = ""')
                    ]
                )
            ]
        ), $result);
    }

    public function testFuncClosed()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/signature/funcClosed.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $result = $this->textDocument->signatureHelp(
            new TextDocumentIdentifier($completionUri),
            new Position(6, 10)
        )->wait();

        $this->assertEquals(new SignatureHelp(
            [
                new SignatureInformation(
                    'helpFunc1(int $count = 0)',
                    null,
                    [
                        new ParameterInformation('int $count = 0')
                    ]
                )
            ]
        ), $result);
    }

    public function testFuncNotClosed()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/signature/funcNotClosed.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $result = $this->textDocument->signatureHelp(
            new TextDocumentIdentifier($completionUri),
            new Position(6, 10)
        )->wait();

        $this->assertEquals(new SignatureHelp(
            [
                new SignatureInformation(
                    'helpFunc2(int $count = 0)',
                    null,
                    [
                        new ParameterInformation('int $count = 0')
                    ]
                )
            ]
        ), $result);
    }

    public function testStaticClosed()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/signature/staticClosed.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $result = $this->textDocument->signatureHelp(
            new TextDocumentIdentifier($completionUri),
            new Position(9, 19)
        )->wait();

        $this->assertEquals(new SignatureHelp(
            [
                new SignatureInformation(
                    'method(string $param = "")',
                    null,
                    [
                        new ParameterInformation('string $param = ""')
                    ]
                )
            ]
        ), $result);
    }

    public function testStaticNotClosed()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/signature/staticNotClosed.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $result = $this->textDocument->signatureHelp(
            new TextDocumentIdentifier($completionUri),
            new Position(9, 19)
        )->wait();

        $this->assertEquals(new SignatureHelp(
            [
                new SignatureInformation(
                    'method(string $param = "")',
                    null,
                    [
                        new ParameterInformation('string $param = ""')
                    ]
                )
            ]
        ), $result);
    }

    public function testMethodActiveParam()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/signature/methodActiveParam.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $result = $this->textDocument->signatureHelp(
            new TextDocumentIdentifier($completionUri),
            new Position(14, 21)
        )->wait();

        $this->assertEquals(new SignatureHelp(
            [
                new SignatureInformation(
                    'method(string $param = "", int $count = 0, bool $test = null)',
                    null,
                    [
                        new ParameterInformation('string $param = ""'),
                        new ParameterInformation('int $count = 0'),
                        new ParameterInformation('bool $test = null')
                    ]
                )
            ],
            0,
            1
        ), $result);
    }
}
