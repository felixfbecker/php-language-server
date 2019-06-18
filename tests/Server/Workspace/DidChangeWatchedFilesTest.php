<?php
declare(strict_types=1);

namespace LanguageServer\Tests\Server\Workspace;

use Amp\Deferred;
use Amp\Loop;
use LanguageServer\ContentRetriever\FileSystemContentRetriever;
use LanguageServer\{DefinitionResolver, Event\MessageEvent, LanguageClient, PhpDocumentLoader, Server};
use LanguageServer\Index\{DependenciesIndex, Index, ProjectIndex};
use LanguageServerProtocol\{FileChangeType, FileEvent};
use LanguageServer\Message;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\Tests\Server\ServerTestCase;
use LanguageServer\Server\Workspace;

class DidChangeWatchedFilesTest extends ServerTestCase
{
    public function testDeletingFileClearsAllDiagnostics()
    {
        Loop::run(function () {
            $client = new LanguageClient(new MockProtocolStream(), $writer = new MockProtocolStream());
            $projectIndex = new ProjectIndex($sourceIndex = new Index(), $dependenciesIndex = new DependenciesIndex());
            $definitionResolver = new DefinitionResolver($projectIndex);
            $loader = new PhpDocumentLoader(new FileSystemContentRetriever(), $projectIndex, $definitionResolver);
            $workspace = new Server\Workspace($client, $projectIndex, $dependenciesIndex, $sourceIndex, null, $loader, null);

            $fileEvent = new FileEvent('my uri', FileChangeType::DELETED);

            $isDiagnosticsCleared = false;
            $deferred = new Deferred();
            $writer->addListener('message', function (MessageEvent $messageEvent) use ($deferred, $fileEvent, &$isDiagnosticsCleared) {
                $message = $messageEvent->getMessage();
                if ($message->body->method === "textDocument/publishDiagnostics") {
                    $this->assertEquals($message->body->params->uri, $fileEvent->uri);
                    $this->assertEquals($message->body->params->diagnostics, []);
                    $deferred->resolve(true);
                }
            });

            $workspace->didChangeWatchedFiles([$fileEvent]);

            $this->assertTrue(yield $deferred->promise(), "Deleting file should clear all diagnostics.");
        });
    }
}
