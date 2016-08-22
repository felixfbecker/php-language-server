<?php

namespace LanguageServer\Protocol;

/**
 * The result of a hover request.
 */
class Hover
{
    /**
     * The hover's content
     *
     * @var string|string[]|MarkedString|MarkedString[]
     */
    public $contents;

    /**
     * An optional range
     *
     * @var Range|null
     */
    public $range;
}
