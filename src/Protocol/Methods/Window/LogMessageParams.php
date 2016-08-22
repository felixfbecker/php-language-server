<?php

namespace LanguageServer\Protocol\Window;

use LanguageServer\Protocol\Params;

class LogMessageParams extends Params
{
    /**
     * The message type. See LanguageServer\Protocol\MessageType
     *
     * @var int
     */
    public $type;

    /**
     * The actual message
     *
     * @var string
     */
    public $message;
}
