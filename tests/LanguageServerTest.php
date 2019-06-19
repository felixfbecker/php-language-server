<?php
declare(strict_types=1);

namespace LanguageServer\Tests;

use AdvancedJsonRpc;
use Amp\Deferred;
use Amp\Loop;
use Exception;
use LanguageServer\Event\MessageEvent;
use LanguageServer\LanguageServer;
use LanguageServer\Message;
use LanguageServerProtocol\{ClientCapabilities,
    CompletionOptions,
    InitializeResult,
    MessageType,
    ServerCapabilities,
    SignatureHelpOptions,
    TextDocumentIdentifier,
    TextDocumentItem,
    TextDocumentSyncKind};
use PHPUnit\Framework\TestCase;
use Webmozart\Glob\Glob;
use Webmozart\PathUtil\Path;
use function LanguageServer\pathToUri;

class LanguageServerTest extends TestCase
{
    public function testInitialize()
    {
        Loop::run(function () {
            $server = new LanguageServer(new MockProtocolStream, new MockProtocolStream);
            $result = yield $server->initialize(new ClientCapabilities, __DIR__, getmypid());

            $serverCapabilities = new ServerCapabilities();
            $serverCapabilities->textDocumentSync = TextDocumentSyncKind::FULL;
            $serverCapabilities->documentSymbolProvider = true;
            $serverCapabilities->workspaceSymbolProvider = true;
            $serverCapabilities->definitionProvider = true;
            $serverCapabilities->referencesProvider = true;
            $serverCapabilities->hoverProvider = true;
            $serverCapabilities->completionProvider = new CompletionOptions;
            $serverCapabilities->completionProvider->resolveProvider = false;
            $serverCapabilities->completionProvider->triggerCharacters = ['$', '>'];
            $serverCapabilities->signatureHelpProvider = new SignatureHelpOptions;
            $serverCapabilities->signatureHelpProvider->triggerCharacters = ['(', ','];
            $serverCapabilities->xworkspaceReferencesProvider = true;
            $serverCapabilities->xdefinitionProvider = true;
            $serverCapabilities->xdependenciesProvider = true;

            $this->assertEquals(new InitializeResult($serverCapabilities), $result);
        });
    }

    public function testIndexingWithDirectFileAccess()
    {
        Loop::run(function () {
            $deferred = new Deferred();
            $input = new MockProtocolStream;
            $output = new MockProtocolStream;
            $output->addListener('message', function (MessageEvent $messageEvent) use ($deferred) {
                $msg = $messageEvent->getMessage();
                Loop::defer(function () use ($deferred, $msg) {
                    if ($msg->body->method === 'window/logMessage') {
                        if ($msg->body->params->type === MessageType::ERROR) {
                            $deferred->fail(new Exception($msg->body->params->message));
                        } else if (preg_match('/All \d+ PHP files parsed/', $msg->body->params->message)) {
                            $deferred->resolve(true);
                        }
                    }
                });
            });
            $server = new LanguageServer($input, $output);
            $capabilities = new ClientCapabilities;
            yield $server->initialize($capabilities, realpath(__DIR__ . '/../fixtures'), getmypid());
            $this->assertTrue(yield $deferred->promise());
        });
    }

    public function testIndexingWithFilesAndContentRequests()
    {
        Loop::run(function () {
            $deferred = new Deferred();
            $filesCalled = false;
            $contentCalled = false;
            $rootPath = realpath(__DIR__ . '/../fixtures');
            $input = new MockProtocolStream;
            $output = new MockProtocolStream;
            $run = 1;
            $output->addListener('message', function (MessageEvent $messageEvent) use ($deferred, $input, $rootPath, &$filesCalled, &$contentCalled, &$run) {
                $msg = $messageEvent->getMessage();
                Loop::defer(function () use ($msg, $deferred, $input, $rootPath, &$filesCalled, &$contentCalled, &$run) {
                    if ($msg->body->method === 'textDocument/xcontent') {
                        // Document content requested
                        $contentCalled = true;
                        $textDocumentItem = new TextDocumentItem;
                        $textDocumentItem->uri = $msg->body->params->textDocument->uri;
                        $textDocumentItem->version = 1;
                        $textDocumentItem->languageId = 'php';
                        $textDocumentItem->text = file_get_contents($msg->body->params->textDocument->uri);
                        yield from $input->write(new Message(new AdvancedJsonRpc\SuccessResponse($msg->body->id, $textDocumentItem)));
                    } else if ($msg->body->method === 'workspace/xfiles') {
                        // Files requested
                        $filesCalled = true;
                        $pattern = Path::makeAbsolute('**/*.php', $msg->body->params->base ?? $rootPath);
                        $files = [];
                        foreach (Glob::glob($pattern) as $path) {
                            $files[] = new TextDocumentIdentifier(pathToUri($path));
                        }
                        yield from $input->write(new Message(new AdvancedJsonRpc\SuccessResponse($msg->body->id, $files)));
                    } else if ($msg->body->method === 'window/logMessage') {
                        // Message logged
                        if ($msg->body->params->type === MessageType::ERROR) {
                            // Error happened during indexing, fail test
                            $deferred->fail(new Exception($msg->body->params->message));
                        } else if (preg_match('/All \d+ PHP files parsed/', $msg->body->params->message)) {
                            $deferred->resolve(true);
                        }
                    }
                });
            });
            $server = new LanguageServer($input, $output);
            $capabilities = new ClientCapabilities;
            $capabilities->xfilesProvider = true;
            $capabilities->xcontentProvider = true;
            yield $server->initialize($capabilities, $rootPath, getmypid());
            yield $deferred->promise();
            $this->assertTrue($filesCalled);
            $this->assertTrue($contentCalled);
        });
    }
}
