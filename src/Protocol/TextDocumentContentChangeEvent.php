<?php

namespace LanguageServer\Protocol;

/**
 * An event describing a change to a text document. If range and rangeLength are omitted
 * the new text is considered to be the full content of the document.
 */
class TextDocumentContentChangeEvent
{
    /**
     * The range of the document that changed.
     *
     * @var Range|null
     */
    public $range;

    /**
     * The length of the range that got replaced.
     *
     * @var int|null
     */
    public $rangeLength;

    /**
     * The new text of the document.
     *
     * @var string
     */
    public $text;
}
