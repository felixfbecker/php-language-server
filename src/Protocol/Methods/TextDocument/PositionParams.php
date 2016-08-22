<?php

namespace LanguageServer\Protocol\TextDocument;

use LanguageServer\Protocol\RequestParams;

/*
 * A parameter literal used in requests to pass a text document and a position inside
 * that document.
 */
class TextDocumentPositionParams extends RequestParams
{
    /**
     * The text document.
     *
     * @var TextDocumentIdentifier
     */
    public $textDocument;

    /**
     * The position inside the text document.
     *
     * @var Position
     */
    public $position;
}
