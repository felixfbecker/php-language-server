<?php

namespace LanguageServer;

use LanguageServer\Server\TextDocument;
use LanguageServer\Protocol\{ServerCapabilities, ClientCapabilities, TextDocumentSyncKind, Message};
use LanguageServer\Protocol\InitializeResult;
use AdvancedJsonRpc\{Dispatcher, ResponseError, Response as ResponseBody, Request as RequestBody};

class LanguageServer extends \AdvancedJsonRpc\Dispatcher
{
    /**
     * Handles textDocument/* method calls
     *
     * @var Server\TextDocument
     */
    public $textDocument;

    public $telemetry;
    public $window;
    public $workspace;
    public $completionItem;
    public $codeLens;

    private $protocolReader;
    private $protocolWriter;
    private $client;

    public function __construct(ProtocolReader $reader, ProtocolWriter $writer)
    {
        parent::__construct($this, '/');
        $this->protocolReader = $reader;
        $this->protocolReader->onMessage(function (Message $msg) {
            $err = null;
            $result = null;
            try {
                // Invoke the method handler to get a result
                $result = $this->dispatch($msg->body);
            } catch (ResponseError $e) {
                // If a ResponseError is thrown, send it back in the Response (result will be null)
                $err = $e;
            } catch (Throwable $e) {
                // If an unexpected error occured, send back an INTERNAL_ERROR error response (result will be null)
                $err = new ResponseError(
                    $e->getMessage(),
                    $e->getCode() === 0 ? ErrorCode::INTERNAL_ERROR : $e->getCode(),
                    null,
                    $e
                );
            }
            // Only send a Response for a Request
            // Notifications do not send Responses
            if (RequestBody::isRequest($msg->body)) {
                $this->protocolWriter->write(new Message(new ResponseBody($msg->body->id, $result, $err)));
            }
        });
        $this->protocolWriter = $writer;
        $this->client = new LanguageClient($writer);
        $this->textDocument = new Server\TextDocument($this->client);
    }

    /**
     * The initialize request is sent as the first request from the client to the server.
     *
     * @param string $rootPath The rootPath of the workspace. Is null if no folder is open.
     * @param int $processId The process Id of the parent process that started the server.
     * @param ClientCapabilities $capabilities The capabilities provided by the client (editor)
     * @return InitializeResult
     */
    public function initialize(string $rootPath, int $processId, ClientCapabilities $capabilities): InitializeResult
    {
        $serverCapabilities = new ServerCapabilities();
        // Ask the client to return always full documents (because we need to rebuild the AST from scratch)
        $serverCapabilities->textDocumentSync = TextDocumentSyncKind::FULL;
        // Support "Find all symbols"
        $serverCapabilities->documentSymbolProvider = true;
        return new InitializeResult($serverCapabilities);
    }

    /**
     * The shutdown request is sent from the client to the server. It asks the server to shut down, but to not exit
     * (otherwise the response might not be delivered correctly to the client). There is a separate exit notification that
     * asks the server to exit.
     *
     * @return void
     */
    public function shutdown()
    {

    }

    /**
     * A notification to ask the server to exit its process.
     *
     * @return void
     */
    public function exit()
    {
        exit(0);
    }
}
