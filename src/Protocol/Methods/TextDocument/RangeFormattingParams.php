<?php

namespace LanguageServer\Protocol\TextDocument;

use LanguageServer\Protocol\Params;

class RangeFormattingParams extends Params
{
    /**
     * The document to format.
     *
     * @var LanguageServer\Protocol\TextDocumentIdentifier
     */
    public $textDocument;

    /**
     * The range to format
     *
     * @var LanguageServer\Protocol\Range
     */
    public $range;

    /**
     * The format options
     *
     * @var LanguageServer\Protocol\FormattingOptions
     */
    public $options;
}
