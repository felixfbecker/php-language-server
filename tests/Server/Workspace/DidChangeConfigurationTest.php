<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\Workspace;

use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\Tests\Server\ServerTestCase;
use LanguageServer\{Server, Client, LanguageClient, Project, PhpDocument, Options};
use LanguageServer\Protocol\{
    Message,
    MessageType,
    TextDocumentItem,
    TextDocumentIdentifier,
    SymbolInformation,
    SymbolKind,
    DiagnosticSeverity,
    FormattingOptions,
    Location,
    Range,
    Position
};
use AdvancedJsonRpc\{Request as RequestBody, Response as ResponseBody};
use function LanguageServer\pathToUri;
use Sabre\Event\Promise;
use Exception;

class DidChangeConfigurationTest extends ServerTestCase
{
    public function testWipingIndex()
    {
        $promise = new Promise;

        $this->projectIndex->on('wipe', function() use ($promise) {
            $promise->fulfill();
        });

        $options = new Options;
        $options->fileTypes = [
            '.inc'
        ];

        $this->workspace->didChangeConfiguration($options);
        $promise->wait();
    }

    public function testReindexingAfterWipe()
    {
        $promise = new Promise;

        $this->output->on('message', function (Message $msg) use ($promise) {
            if ($msg->body->method === 'window/logMessage' && $promise->state === Promise::PENDING) {
                if ($msg->body->params->type === MessageType::ERROR) {
                    $promise->reject(new Exception($msg->body->params->message));
                } elseif (strpos($msg->body->params->message, 'All 0 PHP files parsed') !== false) {
                    $promise->fulfill();
                }
            }
        });

        $options = new Options;
        $options->fileTypes = [
            '.inc'
        ];

        $this->workspace->didChangeConfiguration($options);
        $promise->wait();
    }

    public function testGetChangedOptions()
    {
    }
}
