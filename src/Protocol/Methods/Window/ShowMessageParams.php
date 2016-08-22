<?php

namespace LanguageServer\Protocol\Window;

use LanguageServer\Protocol\Params;

class ShowMessageParams extends Params
{
    /**
     * The message type. See LanguageServer\Protocol\MessageType
     *
     * @var int
     */
    public $type;

    /**
     * The actual message.
     *
     * @var string
     */
    public $message;
}
