<?php

namespace LanguageServer\Protocol;

class Response extends Message
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
     * @var object|null
     */
    public $params;

    /**
     * @var ResponseError|null
     */
    public $error;
}
