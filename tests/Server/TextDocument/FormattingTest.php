<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, Client, LanguageClient, Project};
use LanguageServer\Protocol\{
    TextDocumentIdentifier,
    TextDocumentItem,
    FormattingOptions,
    ClientCapabilities,
    TextEdit,
    Range,
    Position
};
use function LanguageServer\{pathToUri, uriToPath};

class FormattingTest extends TestCase
{
    /**
     * @var Server\TextDocument
     */
    private $textDocument;

    public function setUp()
    {
        $client = new LanguageClient(new MockProtocolStream, new MockProtocolStream);
        $project = new Project($client, new ClientCapabilities);
        $this->textDocument = new Server\TextDocument($project, $client);
    }

    public function testFormatting()
    {
        $client = new LanguageClient(new MockProtocolStream, new MockProtocolStream);
        $project = new Project($client, new ClientCapabilities);
        $textDocument = new Server\TextDocument($project, $client);
        $path = realpath(__DIR__ . '/../../../fixtures/format.php');
        $uri = pathToUri($path);

        // Trigger parsing of source
        $textDocumentItem = new TextDocumentItem();
        $textDocumentItem->uri = $uri;
        $textDocumentItem->languageId = 'php';
        $textDocumentItem->version = 1;
        $textDocumentItem->text = file_get_contents($path);
        $textDocument->didOpen($textDocumentItem);

        // how code should look after formatting
        $expected = file_get_contents(__DIR__ . '/../../../fixtures/format_expected.php');
        // Request formatting
        $result = $textDocument->formatting(new TextDocumentIdentifier($uri), new FormattingOptions())->wait();
        $this->assertEquals([new TextEdit(new Range(new Position(0, 0), new Position(20, 0)), $expected)], $result);
    }
}
