<?php

namespace LanguageServer;

use LanguageServer\Protocol\ProtocolServer;

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
