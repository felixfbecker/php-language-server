<?php

class ResponseError
{
    /**
     * A number indicating the error type that occurred.
     *
     * @var int
     */
    public $code;

    /**
     * A string providing a short description of the error.
     *
     * @var string
     */
    public $message;

    /**
     * A Primitive or Structured value that contains additional information about the
     * error. Can be omitted.
     *
     * @var mixed
     */
    public $data;
}
