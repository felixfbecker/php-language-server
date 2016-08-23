<?php

namespace LanguageServer;

use LanguageServer\Protocol\{ProtocolServer, ServerCapabilities, TextDocumentSyncKind};
use LanguageServer\Protocol\Methods\{InitializeParams, InitializeResult};

class LanguageServer extends ProtocolServer
{
    protected $textDocument;
    protected $telemetry;
    protected $window;
    protected $workspace;
    protected $completionItem;
    protected $codeLens;

    public function __construct($input, $output)
    {
        parent::__construct($input, $output);
        $this->textDocument = new TextDocumentManager();
    }

    protected function initialize(InitializeParams $req): InitializeResult
    {
        $capabilities = new ServerCapabilites();
        // Ask the client to return always full documents (because we need to rebuild the AST from scratch)
        $capabilities->textDocumentSync = TextDocumentSyncKind::FULL;
        // Support "Find all symbols"
        $capabilities->documentSymbolProvider = true;
        $result = new InitializeResult($capabilities);
        return $result;
    }
}
