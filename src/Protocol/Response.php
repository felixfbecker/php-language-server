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
     * @var mixed
     */
    public $result;

    /**
     * @var ResponseError|null
     */
    public $error;

    public function __construct($result, ResponseError $error = null)
    {
        $this->result = $result;
        $this->error = $error;
    }
}
