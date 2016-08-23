<?php

namespace LanguageServer\Protocol\Methods\Initialize;

class InitializeError
{
    /*
     * Indicates whether the client should retry to send the initilize request after
     * showing the message provided in the ResponseError.
     *
     * @var bool
     */
    public $retry;
}
