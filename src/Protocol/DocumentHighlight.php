<?php

namespace LanguageServer\Protocol;

/**
 * A document highlight is a range inside a text document which deserves
 * special attention. Usually a document highlight is visualized by changing
 * the background color of its range.
 */
class DocumentHighlight
{
    /**
     * The range this highlight applies to.
     *
     * @var Range
     */
    public $range;

    /**
     * The highlight kind, default is DocumentHighlightKind::TEXT.
     *
     * @var int|null
     */
    public $kind;
}
