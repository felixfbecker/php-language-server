<?php
declare(strict_types = 1);

namespace LanguageServer\Tests;

use PHPUnit\Framework\TestCase;
use LanguageServer\LanguageServer;
use LanguageServer\Protocol\{Message, ClientCapabilities, TextDocumentSyncKind, MessageType};
use AdvancedJsonRpc;
use Sabre\Event\Promise;
use function LanguageServer\pathToUri;

class LanguageServerTest extends TestCase
{
    public function testInitialize()
    {
        $reader = new MockProtocolStream();
        $writer = new MockProtocolStream();
        $server = new LanguageServer($reader, $writer);
        $msg = null;
        $writer->on('message', function (Message $message) use (&$msg) {
            $msg = $message;
        });
        $reader->write(new Message(new AdvancedJsonRpc\Request(1, 'initialize', [
            'rootPath' => __DIR__,
            'processId' => getmypid(),
            'capabilities' => new ClientCapabilities()
        ])));
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

    public function testIndexing()
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
}
