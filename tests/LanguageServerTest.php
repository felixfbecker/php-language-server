<?php
declare(strict_types = 1);

namespace LanguageServer\Tests;

use PHPUnit\Framework\TestCase;
use LanguageServer\LanguageServer;
use LanguageServer\Protocol\{
    Message,
    ClientCapabilities,
    TextDocumentSyncKind,
    MessageType,
    TextDocumentItem,
    TextDocumentIdentifier,
    InitializeResult,
    ServerCapabilities,
    CompletionOptions
};
use AdvancedJsonRpc;
use Webmozart\Glob\Glob;
use Webmozart\PathUtil\Path;
use Sabre\Event\Promise;
use Exception;
use function LanguageServer\pathToUri;

class LanguageServerTest extends TestCase
{
    public function testInitialize()
    {
        $server = new LanguageServer(new MockProtocolStream, new MockProtocolStream);
        $result = $server->initialize(new ClientCapabilities, __DIR__, getmypid())->wait();

        $serverCapabilities = new ServerCapabilities();
        $serverCapabilities->textDocumentSync = TextDocumentSyncKind::FULL;
        $serverCapabilities->documentSymbolProvider = true;
        $serverCapabilities->workspaceSymbolProvider = true;
        $serverCapabilities->documentFormattingProvider = true;
        $serverCapabilities->definitionProvider = true;
        $serverCapabilities->referencesProvider = true;
        $serverCapabilities->hoverProvider = true;
        $serverCapabilities->completionProvider = new CompletionOptions;
        $serverCapabilities->completionProvider->resolveProvider = false;
        $serverCapabilities->completionProvider->triggerCharacters = ['$', '>'];
        $serverCapabilities->xworkspaceReferencesProvider = true;
        $serverCapabilities->xdefinitionProvider = true;
        $serverCapabilities->xdependenciesProvider = true;

        $this->assertEquals(new InitializeResult($serverCapabilities), $result);
    }

    public function testIndexingWithDirectFileAccess()
    {
        $promise = new Promise;
        $input = new MockProtocolStream;
        $output = new MockProtocolStream;
        $cacheVersionCalled = false;
        $cacheReferenceCalled = false;
        $output->on('message', function (Message $msg) use ($promise, &$cacheVersionCalled, &$cacheReferenceCalled) {
            if ($msg->body->method === 'window/logMessage' && $promise->state === Promise::PENDING) {
                if ($msg->body->params->type === MessageType::ERROR) {
                    $promise->reject(new Exception($msg->body->params->message));
                } else if (preg_match('/All [0-9]+ PHP files parsed/', $msg->body->params->message)) {
                    $promise->fulfill();
                } else if (preg_match('#(Storing|Restored) example/example-version:.* (in|from) cache#', $msg->body->params->message)) {
                    $cacheVersionCalled = true;
                } else if (preg_match('#(Storing|Restored) example/example-reference:.* (in|from) cache#', $msg->body->params->message)) {
                    $cacheReferenceCalled = true;
                }
            }
        });
        $server = new LanguageServer($input, $output);
        $capabilities = new ClientCapabilities;
        $server->initialize($capabilities, realpath(__DIR__ . '/../fixtures'), getmypid());
        $promise->wait();
        $this->assertTrue($cacheVersionCalled);
        $this->assertTrue($cacheReferenceCalled);
    }

    public function testIndexingWithFilesAndContentRequests()
    {
        $promise = new Promise;
        $filesCalled = false;
        $contentCalled = false;
        $rootPath = realpath(__DIR__ . '/../fixtures');
        $input = new MockProtocolStream;
        $output = new MockProtocolStream;
        $run = 1;
        $output->on('message', function (Message $msg) use ($promise, $input, $rootPath, &$filesCalled, &$contentCalled, &$run) {
            if ($msg->body->method === 'textDocument/xcontent') {
                // Document content requested
                $contentCalled = true;
                $textDocumentItem = new TextDocumentItem;
                $textDocumentItem->uri = $msg->body->params->textDocument->uri;
                $textDocumentItem->version = 1;
                $textDocumentItem->languageId = 'php';
                $textDocumentItem->text = file_get_contents($msg->body->params->textDocument->uri);
                $input->write(new Message(new AdvancedJsonRpc\SuccessResponse($msg->body->id, $textDocumentItem)));
            } else if ($msg->body->method === 'workspace/xfiles') {
                // Files requested
                $filesCalled = true;
                $pattern = Path::makeAbsolute('**/*.php', $msg->body->params->base ?? $rootPath);
                $files = [];
                foreach (Glob::glob($pattern) as $path) {
                    $files[] = new TextDocumentIdentifier(pathToUri($path));
                }
                $input->write(new Message(new AdvancedJsonRpc\SuccessResponse($msg->body->id, $files)));
            } else if ($msg->body->method === 'window/logMessage') {
                // Message logged
                if ($msg->body->params->type === MessageType::ERROR) {
                    // Error happened during indexing, fail test
                    if ($promise->state === Promise::PENDING) {
                        $promise->reject(new Exception($msg->body->params->message));
                    }
                } else if (preg_match('/All [0-9]+ PHP files parsed/', $msg->body->params->message)) {
                    $promise->fulfill();
                }
            }
        });
        $server = new LanguageServer($input, $output);
        $capabilities = new ClientCapabilities;
        $capabilities->xfilesProvider = true;
        $capabilities->xcontentProvider = true;
        $server->initialize($capabilities, $rootPath, getmypid());
        $promise->wait();
        $this->assertTrue($filesCalled);
        $this->assertTrue($contentCalled);
    }
}
