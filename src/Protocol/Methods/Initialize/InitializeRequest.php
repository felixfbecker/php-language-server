<?php

namespace LanguageServer\Protocol\Methods\Initialize;

use LanguageServer\Protocol\Request;

/**
 * The initialize request is sent as the first request from the client to the server.
 */
class InitializeRequest extends Request
{
    /**
     * @var InitializeParams
     */
    public $params;
}
