<?php
declare(strict_types = 1);

namespace LanguageServer\Tests;

use PHPUnit\Framework\TestCase;
use LanguageServer\LanguageServer;
use LanguageServer\Protocol\{Message, ClientCapabilities, TextDocumentSyncKind, MessageType, Content};
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
            if ($msg->body->method === 'window/logMessage') {
                if ($msg->body->params->type === MessageType::ERROR) {
                    $promise->reject();
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

    public function testIndexingWithGlobAndContentRequests()
    {
        $promise = new Promise;
        $globCalled = false;
        $contentCalled = false;
        $rootPath = realpath(__DIR__ . '/../fixtures');
        $input = new MockProtocolStream;
        $output = new MockProtocolStream;
        $output->on('message', function (Message $msg) use ($promise, $input, $rootPath, &$globCalled, &$contentCalled) {
            if ($msg->body->method === 'textDocument/xcontent') {
                // Document content requested
                $contentCalled = true;
                $input->write(new Message(new AdvancedJsonRpc\SuccessResponse(
                    $msg->body->id,
                    new Content(file_get_contents($msg->body->params->textDocument->uri))
                )));
            } else if ($msg->body->method === 'workspace/xglob') {
                // Glob requested
                $globCalled = true;
                $files = array_map(
                    '\\LanguageServer\\pathToUri',
                    array_merge(...array_map(function (string $pattern) use ($rootPath) {
                        return Glob::glob(Path::makeAbsolute($pattern, $rootPath));
                    }, $msg->body->params->patterns))
                );
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
        $capabilities->xglobProvider = true;
        $capabilities->xcontentProvider = true;
        $server->initialize(getmypid(), $capabilities, $rootPath);
        $promise->wait();
        $this->assertTrue($globCalled);
        $this->assertTrue($contentCalled);
    }
}
