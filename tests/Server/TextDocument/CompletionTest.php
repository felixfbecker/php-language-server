<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, LanguageClient, Project};
use LanguageServer\Protocol\{TextDocumentIdentifier, Position, ClientCapabilities, CompletionItem, CompletionItemKind};
use function LanguageServer\pathToUri;

class CompletionTest extends TestCase
{
    /**
     * @var Server\TextDocument
     */
    private $textDocument;

    /**
     * @var Project
     */
    private $project;

    public function setUp()
    {
        $client = new LanguageClient(new MockProtocolStream, new MockProtocolStream);
        $this->project = new Project($client, new ClientCapabilities);
        $this->project->loadDocument(pathToUri(__DIR__ . '/../../../fixtures/global_symbols.php'))->wait();
        $this->textDocument = new Server\TextDocument($this->project, $client);
    }

    public function testForPropertiesAndMethods()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/property.php');
        $this->project->openDocument($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(3, 7)
        )->wait();
        $this->assertEquals([
            new CompletionItem(
                'testProperty',
                CompletionItemKind::PROPERTY,
                '\TestClass', // Type of the property
                'Reprehenderit magna velit mollit ipsum do.'
            ),
            new CompletionItem(
                'testMethod',
                CompletionItemKind::METHOD,
                '\TestClass', // Return type of the method
                'Non culpa nostrud mollit esse sunt laboris in irure ullamco cupidatat amet.'
            )
        ], $items);
    }

    public function testForVariables()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/variable.php');
        $this->project->openDocument($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(8, 5)
        )->wait();
        $this->assertEquals([
            new CompletionItem('$var', CompletionItemKind::VARIABLE, 'int'),
            new CompletionItem('$param', CompletionItemKind::VARIABLE, 'string|null', 'A parameter')
        ], $items);
    }
}
