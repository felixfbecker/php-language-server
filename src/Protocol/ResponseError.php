<?php

namespace LanguageServer\Protocol;

use Exception;

class ResponseError extends Exception
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

    public function __construct(string $message, int $code = ErrorCode::INTERNAL_ERROR, $data = null, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
    }
}
