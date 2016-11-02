<?php
declare(strict_types = 1);

namespace LanguageServer\Tests;

use PHPUnit\Framework\TestCase;
use LanguageServer\LanguageServer;
use LanguageServer\Protocol\{Message, ClientCapabilities, TextDocumentSyncKind};
use AdvancedJsonRpc;

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
                'completionProvider' => (object)[
                    'resolveProvider' => true,
                    'triggerCharacters' => [
                        ':',
                        '$',
                        '>',
                    ]
                ],
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
}
