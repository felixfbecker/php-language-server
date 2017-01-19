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
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/signatureHelp/methodClosed.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $result = $this->textDocument->signatureHelp(
            new TextDocumentIdentifier($completionUri),
            new Position(9, 22)
        )->wait();

        $help = new SignatureHelp;
        $help->signatures = [];
        $info = new SignatureInformation;
        $help->signatures[] = $info;
        $info->label = 'method';
        $info->parameters = [];
        $param = new ParameterInformation;
        $info->parameters[] = $param;
        $param->label = 'string $param = ""';

        $this->assertEquals($help, $result);
    }

    public function testMethodNotClosed()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/signatureHelp/methodNotClosed.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $result = $this->textDocument->signatureHelp(
            new TextDocumentIdentifier($completionUri),
            new Position(9, 22)
        )->wait();

        $help = new SignatureHelp;
        $help->signatures = [];
        $info = new SignatureInformation;
        $help->signatures[] = $info;
        $info->label = 'method';
        $info->parameters = [];
        $param = new ParameterInformation;
        $info->parameters[] = $param;
        $param->label = 'string $param = ""';

        $this->assertEquals($help, $result);
    }

    public function funcClosed()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/signatureHelp/funcClosed.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $result = $this->textDocument->signatureHelp(
            new TextDocumentIdentifier($completionUri),
            new Position(5, 10)
        )->wait();

        $help = new SignatureHelp;
        $help->signatures = [];
        $info = new SignatureInformation;
        $help->signatures[] = $info;
        $info->label = 'helpFunc1';
        $info->parameters = [];
        $param = new ParameterInformation;
        $info->parameters[] = $param;
        $param->label = 'int $count = 0';

        $this->assertEquals($help, $result);
    }

    public function funcNotClosed()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/signatureHelp/funcNotClosed.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $result = $this->textDocument->signatureHelp(
            new TextDocumentIdentifier($completionUri),
            new Position(5, 10)
        )->wait();

        $help = new SignatureHelp;
        $help->signatures = [];
        $info = new SignatureInformation;
        $help->signatures[] = $info;
        $info->label = 'helpFunc2';
        $info->parameters = [];
        $param = new ParameterInformation;
        $info->parameters[] = $param;
        $param->label = 'int $count = 0';

        $this->assertEquals($help, $result);
    }

    public function staticClosed()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/signatureHelp/staticClosed.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $result = $this->textDocument->signatureHelp(
            new TextDocumentIdentifier($completionUri),
            new Position(9, 19)
        )->wait();

        $help = new SignatureHelp;
        $help->signatures = [];
        $info = new SignatureInformation;
        $help->signatures[] = $info;
        $info->label = 'method';
        $info->parameters = [];
        $param = new ParameterInformation;
        $info->parameters[] = $param;
        $param->label = 'string $param = ""';

        $this->assertEquals($help, $result);
    }

    public function staticNotClosed()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/signatureHelp/staticNotClosed.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $result = $this->textDocument->signatureHelp(
            new TextDocumentIdentifier($completionUri),
            new Position(9, 19)
        )->wait();

        $help = new SignatureHelp;
        $help->signatures = [];
        $info = new SignatureInformation;
        $help->signatures[] = $info;
        $info->label = 'method';
        $info->parameters = [];
        $param = new ParameterInformation;
        $info->parameters[] = $param;
        $param->label = 'string $param = ""';

        $this->assertEquals($help, $result);
    }
}
