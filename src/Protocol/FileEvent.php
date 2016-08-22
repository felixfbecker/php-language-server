<?php

namespace LanguageServer\Protocol\TextDocument;

/**
 * An event describing a file change.
 */
class FileEvent
{
    /**
     * The file's URI.
     *
     * @var string
     */
    public $uri;

    /**
     * The change type.
     *
     * @var int
     */
    public $type;
}
