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
     * @var string
     */
    private $completionUri;

    public function setUp()
    {
        $client = new LanguageClient(new MockProtocolStream, new MockProtocolStream);
        $project = new Project($client, new ClientCapabilities);
        $this->completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion.php');
        $project->loadDocument(pathToUri(__DIR__ . '/../../../fixtures/global_symbols.php'));
        $project->openDocument($this->completionUri, file_get_contents($this->completionUri));
        $this->textDocument = new Server\TextDocument($project, $client);
    }

    public function testCompletion()
    {
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($this->completionUri),
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
}
