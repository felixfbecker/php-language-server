<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, Client, LanguageClient, Project};
use LanguageServer\Protocol\{
    TextDocumentIdentifier,
    TextDocumentItem,
    VersionedTextDocumentIdentifier,
    TextDocumentContentChangeEvent,
    Range,
    Position
};

class DidChangeTest extends TestCase
{
    public function test()
    {
        $client = new LanguageClient(new MockProtocolStream, new MockProtocolStream);
        $project = new Project($client);
        $textDocument = new Server\TextDocument($project, $client);
        $phpDocument = $project->openDocument('whatever', "<?php\necho 'Hello, World'\n");

        $identifier = new VersionedTextDocumentIdentifier('whatever');
        $changeEvent = new TextDocumentContentChangeEvent();
        $changeEvent->range = new Range(new Position(0, 0), new Position(9999, 9999));
        $changeEvent->rangeLength = 9999;
        $changeEvent->text = "<?php\necho 'Goodbye, World'\n";

        $textDocument->didChange($identifier, [$changeEvent]);

        $this->assertEquals("<?php\necho 'Goodbye, World'\n", $phpDocument->getContent());
    }
}
