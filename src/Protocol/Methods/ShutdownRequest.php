<?php

namespace LanguageServer\Protocol\Methods;

use LanguageServer\Protocol\Request;

/**
 * The shutdown request is sent from the client to the server. It asks the server to shut down, but to not exit
 * (otherwise the response might not be delivered correctly to the client). There is a separate exit notification that
 * asks the server to exit.
 */
class ShutdownRequest extends Request
{
    /**
     * @var null
     */
    public $params;
}
