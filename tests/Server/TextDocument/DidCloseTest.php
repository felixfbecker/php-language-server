<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, Client, LanguageClient, Project};
use LanguageServer\Protocol\{TextDocumentItem, TextDocumentIdentifier};
use Exception;

class DidCloseTest extends TestCase
{
    public function test()
    {
        $client = new LanguageClient(new MockProtocolStream, new MockProtocolStream);
        $project = new Project($client);
        $textDocument = new Server\TextDocument($project, $client);
        $phpDocument = $project->openDocument('whatever', 'hello world');

        $textDocumentItem = new TextDocumentItem();
        $textDocumentItem->uri = 'whatever';
        $textDocumentItem->languageId = 'php';
        $textDocumentItem->version = 1;
        $textDocumentItem->text = 'hello world';
        $textDocument->didOpen($textDocumentItem);

        $textDocument->didClose(new TextDocumentIdentifier($textDocumentItem->uri));

        $this->assertFalse($project->isDocumentOpen($textDocumentItem->uri));
    }
}
