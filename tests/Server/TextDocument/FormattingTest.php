<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, Client, LanguageClient, PhpDocumentLoader, DefinitionResolver};
use LanguageServer\Index\{Index, ProjectIndex, DependenciesIndex};
use LanguageServer\ContentRetriever\FileSystemContentRetriever;
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
    public function testFormatting()
    {
        $projectIndex = new ProjectIndex(new Index, new DependenciesIndex);
        $client = new LanguageClient(new MockProtocolStream, new MockProtocolStream);
        $definitionResolver = new DefinitionResolver($projectIndex);
        $loader = new PhpDocumentLoader(new FileSystemContentRetriever, $projectIndex, $definitionResolver);
        $textDocument = new Server\TextDocument($loader, $definitionResolver, $client, $projectIndex);

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
