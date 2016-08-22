<?php

namespace LanguageServer\Protocol\TextDocument;

use LanguageServer\Protocol\Params;

class OnTypeFormattingParams extends Params
{
    /**
     * The document to format.
     *
     * @var LanguageServer\Protocol\TextDocumentIdentifier
     */
    public $textDocument;

    /**
     * The position at which this request was sent.
     *
     * @var LanguageServer\Protocol\Position
     */
    public $position;

    /**
     * The character that has been typed.
     *
     * @var string
     */
    public $ch;

    /**
     * The format options.
     *
     * @var LanguageServer\Protocol\FormattingOptions
     */
    public $options;
}
