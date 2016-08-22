<?php

namespace LanguageServer;

use LanguageServer\Protocol\{ProtocolServer, ServerCapabilities};
use LanguageServer\Protocol\Methods\Initialize\{InitializeRequest, InitializeResult, InitializeResponse};

class LanguageServer extends ProtocolServer
{
    public function initialize(InitializeRequest $req): InitializeResponse
    {
        $result = new InitializeResult;
        $result->capabilites = new ServerCapabilities;
        return new InitializeResponse($result);
    }

    public function shutdown
}
