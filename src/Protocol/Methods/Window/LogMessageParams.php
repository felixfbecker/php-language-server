<?php

namespace LanguageServer\Protocol\Window;

use LanguageServer\Protocol\RequestParams;

/**
 * The log message notification is sent from the server to the client to ask the
 * client to log a particular message.
 */
class LogMessageParams extends RequestParams
{
    /**
     * The message type. See {@link MessageType}
     *
     * @var number
     */
    public $type;

    /**
     * The actual message
     *
     * @var string
     */
    public $message;
}
