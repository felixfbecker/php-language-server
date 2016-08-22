<?php

namespace LanguageServer\Protocol;

/**
 * A request message to describe a request between the client and the server. Every processed request must send a
 * response back to the sender of the request.
 */
class Request extends Message
{
    /**
     * @var int|string
     */
    public $id;

    /**
     * @var string
     */
    public $method;

    /**
     * @var Params
     */
    public $params;
}
