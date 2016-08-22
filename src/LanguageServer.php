<?php

namespace LanguageServer;

use LanguageServer\Protocol\ProtocolServer;

/**
 * Enum
 */
abstract class ParsingMode {
    const HEADERS = 1;
    const BODY = 2;
}

class LanguageServer extends ProtocolServer
{
    public function listen()
    {
        $this->on('initialize', function (InitializeRequest $req) {
            $res = new InitializeResponse();
            $this->sendResponse($res);
        });
        parent::listen();
    }
}
