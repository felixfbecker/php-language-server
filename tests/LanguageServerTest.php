<?php
declare(strict_types = 1);

namespace LanguageServer\Tests;

use PHPUnit\Framework\TestCase;
use LanguageServer\LanguageServer;
use LanguageServer\Protocol\{
    Message, ClientCapabilities, TextDocumentSyncKind, MessageType, TextDocumentItem, TextDocumentIdentifier};
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
        $reader = new MockProtocolStream();
        $writer = new MockProtocolStream();
        $server = new LanguageServer($reader, $writer);
        $promise = new Promise;
        $writer->once('message', [$promise, 'fulfill']);
        $reader->write(new Message(new AdvancedJsonRpc\Request(1, 'initialize', [
            'rootPath' => __DIR__,
            'processId' => getmypid(),
            'capabilities' => new ClientCapabilities()
        ])));
        $msg = $promise->wait();
        $this->assertNotNull($msg, 'message event should be emitted');
        $this->assertInstanceOf(AdvancedJsonRpc\SuccessResponse::class, $msg->body);
        $this->assertEquals((object)[
            'capabilities' => (object)[
                'textDocumentSync' => TextDocumentSyncKind::FULL,
                'documentSymbolProvider' => true,
                'hoverProvider' => true,
                'completionProvider' => null,
                'signatureHelpProvider' => null,
                'definitionProvider' => true,
                'referencesProvider' => true,
                'documentHighlightProvider' => null,
                'workspaceSymbolProvider' => true,
                'codeActionProvider' => null,
                'codeLensProvider' => null,
                'documentFormattingProvider' => true,
                'documentRangeFormattingProvider' => null,
                'documentOnTypeFormattingProvider' => null,
                'renameProvider' => null
            ]
        ], $msg->body->result);
    }

    public function testIndexingWithDirectFileAccess()
    {
        $promise = new Promise;
        $input = new MockProtocolStream;
        $output = new MockProtocolStream;
        $output->on('message', function (Message $msg) use ($promise) {
            if ($msg->body->method === 'window/logMessage' && $promise->state === Promise::PENDING) {
                if ($msg->body->params->type === MessageType::ERROR) {
                    $promise->reject(new Exception($msg->body->params->message));
                } else if (strpos($msg->body->params->message, 'All 10 PHP files parsed') !== false) {
                    $promise->fulfill();
                }
            }
        });
        $server = new LanguageServer($input, $output);
        $capabilities = new ClientCapabilities;
        $server->initialize(getmypid(), $capabilities, realpath(__DIR__ . '/../fixtures'));
        $promise->wait();
    }

    public function testIndexingWithFilesAndContentRequests()
    {
        $promise = new Promise;
        $filesCalled = false;
        $contentCalled = false;
        $rootPath = realpath(__DIR__ . '/../fixtures');
        $input = new MockProtocolStream;
        $output = new MockProtocolStream;
        $output->on('message', function (Message $msg) use ($promise, $input, $rootPath, &$filesCalled, &$contentCalled) {
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
                } else if (strpos($msg->body->params->message, 'All 10 PHP files parsed') !== false) {
                    // Indexing finished
                    $promise->fulfill();
                }
            }
        });
        $server = new LanguageServer($input, $output);
        $capabilities = new ClientCapabilities;
        $capabilities->xfilesProvider = true;
        $capabilities->xcontentProvider = true;
        $server->initialize(getmypid(), $capabilities, $rootPath);
        $promise->wait();
        $this->assertTrue($filesCalled);
        $this->assertTrue($contentCalled);
    }
}
