<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, Client, LanguageClient, Project};
use LanguageServer\Protocol\{TextDocumentIdentifier, TextDocumentItem, FormattingOptions};

class FormattingTest extends TestCase
{
    /**
     * @var Server\TextDocument
     */
    private $textDocument;

    public function setUp()
    {
        $client = new LanguageClient(new MockProtocolStream());
        $project = new Project($client);
        $this->textDocument = new Server\TextDocument($project, $client);
    }

    public function test()
    {
        $client = new LanguageClient(new MockProtocolStream());
        $project = new Project($client);
        $textDocument = new Server\TextDocument($project, $client);

        // Trigger parsing of source
        $textDocumentItem = new TextDocumentItem();
        $textDocumentItem->uri = 'whatever';
        $textDocumentItem->languageId = 'php';
        $textDocumentItem->version = 1;
        $textDocumentItem->text = file_get_contents(__DIR__ . '/../../../fixtures/format.php');
        $textDocument->didOpen($textDocumentItem);

        // how code should look after formatting
        $expected = file_get_contents(__DIR__ . '/../../../fixtures/format_expected.php');
        // Request formatting
        $result = $textDocument->formatting(new TextDocumentIdentifier('whatever'), new FormattingOptions());
        $this->assertEquals([0 => [
            'range' => [
                'start' => [
                    'line' => 0,
                    'character' => 0
                ],
                'end' => [
                    'line' => PHP_INT_MAX,
                    'character' => PHP_INT_MAX
                ]
            ],
            'newText' => $expected
        ]], json_decode(json_encode($result), true));
    }
}
