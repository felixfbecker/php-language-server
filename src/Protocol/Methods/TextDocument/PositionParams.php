<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Params;

/**
 * A parameter literal used in requests to pass a text document and a position inside
 * that document.
 */
class TextDocumentPositionParams extends Params
{
    /**
     * The text document.
     *
     * @var LanguageServer\Protocol\TextDocumentIdentifier
     */
    public $textDocument;

    /**
     * The position inside the text document.
     *
     * @var LanguageServer\Protocol\Position
     */
    public $position;
}
