<?php

namespace LanguageServer\Protocol;

/**
 * Position in a text document expressed as zero-based line and character offset.
 */
class Position
{
    /**
     * Line position in a document (zero-based).
     *
     * @var int
     */
    public $line;

    /**
     * Character offset on a line in a document (zero-based).
     *
     * @var int
     */
    public $character;
}
