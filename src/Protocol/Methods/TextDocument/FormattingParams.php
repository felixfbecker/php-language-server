<?php

namespace LanguageServer\Protocol\TextDocument;

use LanguageServer\Protocol\Params;

class FormattingParams extends Params
{
    /**
     * The document to format.
     *
     * @var LanguageServer\Protocol\TextDocumentIdentifier
     */
    public $textDocument;

    /**
     * The format options.
     *
     * @var LanguageServer\Protocol\FormattingOptions
     */
    public $options;
}
