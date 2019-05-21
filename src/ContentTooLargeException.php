<?php
declare(strict_types = 1);

namespace LanguageServer;

/**
 * Thrown when the document content is not parsed because it exceeds the size limit
 */
class ContentTooLargeException extends \Exception
{
    /**
     * The limit of content
     *
     * @var int
     */
    public static $limit = 1000000;

    /**
     * The URI of the file that exceeded the limit
     *
     * @var string
     */
    public $uri;

    /**
     * The size of the file in bytes
     *
     * @var int
     */
    public $size;

    /**
     * @param string     $uri      The URI of the file that exceeded the limit
     * @param int        $size     The size of the file in bytes
     * @param \Throwable $previous The previous exception used for the exception chaining.
     */
    public function __construct(string $uri, int $size, \Throwable $previous = null)
    {
        $this->uri = $uri;
        $this->size = $size;
        $limit = self::$limit;
        parent::__construct("$uri exceeds size limit of $limit bytes ($size)", 0, $previous);
    }
}
